<?php
namespace local_padimport\util;

defined('MOODLE_INTERNAL') || die();
global $CFG;

$autoload = $CFG->dirroot . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once($autoload);
}

use PhpOffice\PhpSpreadsheet\IOFactory;

class pad_parser {

    private function padimport_norm(string $s): string {
        $s = trim(mb_strtolower($s));
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    private function padimport_build_rea_banner_html(int $rean, string $text): string {
        $text = preg_replace("/\R+/u", " ", $text);        // \R = cualquier salto de línea
        $text = preg_replace('/\s{2,}/u', ' ', $text);     // colapsa espacios dobles
        $safe = htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return
            '<!-- Banner REA con texto editable sobre la zona blanca -->'
            . '<div style="background-image: url(\'https://pregrado.ucundinamarca.edu.co/draftfile.php/111582/user/draft/31858912/banner-REA-st.png\');'
            . 'background-size: 100% 100%; background-repeat: no-repeat; background-position: center; width: 100%; max-width: 1600px; margin: 0 auto; min-height: 320px; position: relative; font-family: Arial, Helvetica, sans-serif;">'
            . '<div style="position: absolute; left: 30px; top: 30px; max-width: 340px; padding: 8px 0; color: #333; overflow: hidden;" contenteditable="true"> '
            . '<div style="font-weight: 800; font-size: 26px; line-height: 1.2; color: #00a79b; margin: 0 0 10px;">REA ' . (int)$rean . '</div>'
            . $safe
            . '</div></div>';
    }

    /**
     * Busca la tabla "REA ESPECÍFICOS" que tiene columnas "Consecutivo" y "Nombre"
     * y devuelve: [1 => texto, 2 => texto, 3 => texto]
     */
    private function extract_rea_texts_from_table($sheet, int $maxRow, int $maxCol): array {
        $reaTexts = [];

        $consecCol = null;
        $nameCol   = null;
        $headerRow = null;

        // Normalizador simple para comparar encabezados
        $norm = function(string $s): string {
            $s = trim(mb_strtolower($s));
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        // 1) Encontrar fila donde estén "Consecutivo" y "Nombre"
        for ($r = 1; $r <= $maxRow; $r++) {
            $foundConsec = false;
            $foundNombre = false;

            for ($c = 1; $c <= $maxCol; $c++) {
                $v = $sheet->getCell([$c, $r])->getValue();
                if ($v === null) continue;

                $txt = $norm((string)$v);
                if ($txt === 'consecutivo') {
                    $foundConsec = true;
                    $consecCol = $c;
                }
                if ($txt === 'nombre') {
                    $foundNombre = true;
                    $nameCol = $c;
                }
            }

            if ($foundConsec && $foundNombre && $consecCol !== null && $nameCol !== null) {
                $headerRow = $r;
                break;
            }
        }

        if ($headerRow === null) {
            return $reaTexts; // no encontró la tabla
        }

        // 2) Leer filas siguientes (buscamos consecutivo 1,2,3)
        for ($r = $headerRow + 1; $r <= min($headerRow + 30, $maxRow); $r++) {
            $nraw = $sheet->getCell([$consecCol, $r])->getValue();
            $traw = $sheet->getCell([$nameCol, $r])->getValue();

            $n = is_numeric($nraw) ? (int)$nraw : 0;
            $text = trim((string)($traw ?? ''));

            if (in_array($n, [1,2,3], true) && $text !== '' && empty($reaTexts[$n])) {
                $reaTexts[$n] = $text;
            }

            if (count($reaTexts) === 3) {
                break;
            }
        }

        return $reaTexts;
    }

    public function parse_blocks_v19(string $excelfilepath): array {
        $reader = IOFactory::createReaderForFile($excelfilepath);
        $spreadsheet = $reader->load($excelfilepath);

        // Elegir automáticamente la hoja que contiene "Actividad:" o "Rea Específico"
        $sheet = null;
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $rmax = min(200, (int)$ws->getHighestRow());
            $cmax = min(
                40,
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestColumn())
            );

            $found = false;
            for ($r = 1; $r <= $rmax && !$found; $r++) {
                for ($c = 1; $c <= $cmax; $c++) {
                    $v = $ws->getCell([$c, $r])->getValue();
                    if ($v === null) continue;

                    $txt = (string)$v;
                    if (stripos($txt, 'Actividad:') !== false || stripos($txt, 'Rea Espec') !== false) {
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) { $sheet = $ws; break; }
        }

        if ($sheet === null) {
            $sheet = $spreadsheet->getSheet(0); // fallback
        }

        $maxRow = (int)$sheet->getHighestRow();
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $model = ['tabs' => []];
        $tabsIndex = [];

        $currentTab = 'CADI';

        // Guardamos actividad pendiente hasta encontrar "Descripción"
        $pendingTitle = '';
        $pendingTab   = '';

        // Track del máximo REA real detectado (1/2/3) para no crear tabs extra
        $maxReaDetected = 0;

        // Normalizador (espacios raros + saltos de línea)
        $norm = function(string $s): string {
            $s = str_replace("\xC2\xA0", " ", $s);   // NBSP
            $s = preg_replace("/\R+/u", " ", $s);    // saltos de línea
            $s = trim(mb_strtolower($s, 'UTF-8'));
            $s = preg_replace('/\s+/u', ' ', $s);
            return $s;
        };

        // Compatible: getCell([col,row]) (1-based)
        $getCell = function(int $r, int $c) use ($sheet): string {
            $v = $sheet->getCell([$c, $r])->getValue();
            if ($v === null) return '';
            return trim((string)$v);
        };

        $ensureTab = function(string $tabname) use (&$model, &$tabsIndex): void {
            if (!isset($tabsIndex[$tabname])) {
                $tabsIndex[$tabname] = count($model['tabs']);
                $model['tabs'][] = [
                    'name'  => $tabname,
                    'items' => []
                ];
            }
        };

        $addAssign = function(string $tabname, string $title, string $descHtml) use (&$model, &$tabsIndex, $ensureTab): void {
            $ensureTab($tabname);
            $idx = $tabsIndex[$tabname];

            $model['tabs'][$idx]['items'][] = [
                'type'    => 'assign',
                'title'   => $title !== '' ? $title : 'Tarea (importada)',
                'html'    => $descHtml !== '' ? $descHtml : 'Sin descripción.',
                'daysdue' => 14
            ];
        };

        // =======================
        // 1) RECORRER EXCEL PARA CREAR TAREAS
        //    Regla:
        //    - "Rea Específico" + a la derecha "1:"/"2:"/"3:" => define REA actual
        //    - "Actividad: X" => título = X
        //    - Fila con "Descripción" => contenido = celda derecha
        // =======================
        for ($r = 1; $r <= $maxRow; $r++) {

            // 1.1) Detectar "Rea Específico" en cualquier celda de la fila (por merges)
            $foundReaEspecifico = false;
            $foundCol = 0;

            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = $getCell($r, $c);
                if ($cell === '') continue;

                $cellNorm = $norm($cell);

                if (preg_match('/^rea\s*espec/i', $cellNorm)) {
                    $foundReaEspecifico = true;
                    $foundCol = $c;
                    break;
                }
            }

            if ($foundReaEspecifico) {

                // leer el número real del REA desde la derecha: "1:" / "2:" / "3:"
                $reaNum = 0;

                // Busca en las celdas a la derecha (hasta 5 columnas) un patrón tipo "1:"
                for ($cc = $foundCol + 1; $cc <= min($foundCol + 5, $maxCol); $cc++) {
                    $right = $getCell($r, $cc);
                    if ($right === '') continue;

                    if (preg_match('/^\s*([123])\s*:/u', $right, $m)) {
                        $reaNum = (int)$m[1];
                        break;
                    }
                }

                // Fallback: por si el "1:" está en otra celda de la fila
                if ($reaNum === 0) {
                    $rowText = '';
                    for ($k = 1; $k <= $maxCol; $k++) {
                        $rowText .= ' ' . $getCell($r, $k);
                    }
                    if (preg_match('/\b([123])\s*:/u', $rowText, $m2)) {
                        $reaNum = (int)$m2[1];
                    }
                }

                // Si logró leer número, cambia el tab; si no, ignora esa aparición extra
                if ($reaNum > 0) {
                    $currentTab = 'REA ' . $reaNum;
                    $ensureTab($currentTab);
                    if ($reaNum > $maxReaDetected) $maxReaDetected = $reaNum;
                }

                // Limpia actividad pendiente por si venía arrastrada
                $pendingTitle = '';
                $pendingTab = '';
                continue;
            }

            // 1.2) (Opcional) Detectar "Etapa X" si aparece en el excel
            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = $getCell($r, $c);
                if ($cell === '') continue;
                if (preg_match('/^etapa\s*(\d+)/i', $cell, $m)) {
                    $currentTab = 'Etapa ' . ((int)$m[1]);
                    $ensureTab($currentTab);
                    break;
                }
            }

            // 1.3) Detectar "Actividad:" (título) en cualquier celda de la fila
            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = $getCell($r, $c);
                if ($cell === '') continue;

                $cellNorm = $norm($cell);
                if (str_starts_with($cellNorm, 'actividad:')) {
                    $pendingTitle = trim(preg_replace('/^Actividad:\s*/i', '', $cell));
                    $pendingTab = $currentTab; // guardar REA actual
                    break;
                }
            }

            // 1.4) Si hay actividad pendiente, buscar "Descripción" y tomar la celda a la derecha
            if ($pendingTitle !== '') {
                for ($c = 1; $c <= $maxCol; $c++) {
                    $left = $getCell($r, $c);
                    if ($left === '') continue;

                    $leftNorm = $norm($left);

                    // coincide con "Descripción" (con o sin texto adicional)
                    if (
                        $leftNorm === 'descripcion' || str_starts_with($leftNorm, 'descripcion') ||
                        $leftNorm === 'descripción' || str_starts_with($leftNorm, 'descripción')
                    ) {
                        // celda inmediatamente a la derecha
                        $desc = $getCell($r, $c + 1);

                        // si está vacía (por merges), busca la primera no-vacía más a la derecha
                        if ($desc === '') {
                            for ($cc = $c + 2; $cc <= $maxCol; $cc++) {
                                $right = $getCell($r, $cc);
                                if ($right !== '') { $desc = $right; break; }
                            }
                        }

                        $descHtml = nl2br(htmlspecialchars(
                            $desc !== '' ? $desc : 'Sin descripción.',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ));

                        // Crear tarea en el REA actual (pendingTab)
                        $addAssign($pendingTab !== '' ? $pendingTab : $currentTab, $pendingTitle, $descHtml);

                        // limpiar para no duplicar
                        $pendingTitle = '';
                        $pendingTab = '';
                        break;
                    }
                }
            }
        }

        // =======================
        // 2) INSERTAR BANNER REA 1/2/3 DESDE LA TABLA "REA ESPECÍFICOS" (si existe)
        //    Solo crea REA hasta el máximo detectado (por si el PAD solo trae REA 1 y 2)
        // =======================
        $reaTexts = $this->extract_rea_texts_from_table($sheet, $maxRow, $maxCol);

        $maxReaForTabs = max(1, min(3, (int)$maxReaDetected));
        for ($i = 1; $i <= $maxReaForTabs; $i++) {
            $ensureTab('REA ' . $i);
        }

        if (!empty($reaTexts)) {
            foreach ($model['tabs'] as &$tab) {
                $tabname = (string)($tab['name'] ?? '');
                if (preg_match('/^\s*REA\s*([123])\s*$/i', $tabname, $mm)) {
                    $n = (int)$mm[1];
                    if ($n <= $maxReaForTabs && !empty($reaTexts[$n])) {
                        $bannerhtml = $this->padimport_build_rea_banner_html($n, $reaTexts[$n]);
                        $tab['items'] = $tab['items'] ?? [];
                        array_unshift($tab['items'], [
                            'type' => 'label',
                            'html' => $bannerhtml
                        ]);
                    }
                }
            }
            unset($tab);
        }

        return $model;
    }

    // Dejo tus funciones existentes por si las usas en otro lado, no afectan.
    private function extract_activity_blocks(array $lines): array {
        $blocks = [];
        $current = null;

        $flush = function() use (&$blocks, &$current) {
            if ($current === null) return;

            $text = trim(implode("\n", $current['lines']));
            if ($text === '') { $current = null; return; }

            $html = nl2br(s($text));

            $blocks[] = [
                'type' => 'label',
                'title' => $current['title'] ?? 'Contenido',
                'html' => '<h4>' . s($current['title'] ?? 'Contenido') . '</h4><div>' . $html . '</div>',
            ];

            $u = mb_strtoupper($text);
            if (mb_strpos($u, 'TAREA') !== false) {
                $blocks[] = [
                    'type' => 'assign',
                    'title' => 'Tarea: ' . ($current['title'] ?? 'Entrega'),
                    'html' => $html,
                    'daysdue' => 14,
                ];
            }
            if (mb_strpos($u, 'CUESTIONARIO') !== false) {
                $blocks[] = [
                    'type' => 'quiz',
                    'title' => 'Cuestionario: ' . ($current['title'] ?? 'Evaluación'),
                    'html' => $html,
                    'daysdue' => 14,
                ];
            }

            $current = null;
        };

        foreach ($lines as $line) {
            $u = mb_strtoupper($line);

            if (mb_strpos($u, 'ACTIVIDAD:') !== false || mb_strpos($u, 'EXPERIENCIA:') !== false) {
                $flush();
                $title = preg_replace('/^\s*(Actividad:|Experiencia:)\s*/iu', '', $line);
                $current = ['title' => trim($title), 'lines' => [$line]];
                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        $flush();
        return $blocks;
    }

    private function lines_to_html(array $lines): string {
        $safe = [];
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l === '') continue;
            $safe[] = s($l);
        }
        return '<div>' . implode('<br>', $safe) . '</div>';
    }
}
