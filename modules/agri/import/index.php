<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin']);
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');

$user = current_user();
ensure_family_upgrade_schema($conn);

@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '1024M');
@ignore_user_abort(true);

$results = [
    'created_households'=>0,'updated_households'=>0,'created_members'=>0,'updated_members'=>0,
    'created_interviews'=>0,'updated_interviews'=>0,'created_crops'=>0,'created_monitoring'=>0,'issues'=>[]
];
$previewReport = null;

function import_template_csv(string $type): string {
    if ($type === 'monitoring') {
        return "household_code,household_name,crop_name,monitoring_date,tree_count_observed,fruiting_status,crop_condition,needs_rehabilitation,harvest_kg,address_location,notes\nHH-000001,Milo Family,Mango,2026-04-01,10,Fruiting,Good,no,25.5,Purok 1 sample plot,Routine visit\n";
    }
    return "barangay,hh_no,last_name,first_name,middle_name,ext,purok_sitio,date_of_birth,place_of_birth,age_bracket,civil_status,sex,weight,height,educational_attainment,citizenship,language_spoken,religious_affiliation,occupation,employment_status,ofw_details,current_skill,additional_skill_to_acquire,unemployed_current_skill,unemployed_additional_skill,average_monthly_income,emerging_diseases,disability,relation_to_hh_heads,remarks\nImelda,1,Alogbate,Richard,Olorvida,,Proper,1986-08-20,Matag-ob Leyte,39,Married,Male,,,College Level,Filipino,Cebuano,Catholic,Farmer,Regular,,Farming,,,\n";
}

function import_normalize_header(string $value): string {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = strtolower(trim((string)$value));
    $value = str_replace(['&', '/', '-', '.', ',', '(', ')'], [' and ', ' ', ' ', ' ', ' ', ' ', ' '], $value);
    $value = preg_replace('/\s+/', '_', $value);
    return preg_replace('/[^a-z0-9_]+/', '', $value);
}

function import_alias_map(): array {
    return [
        'hh_no' => ['hh_no', 'hh_no_', 'hh_no__', 'hhno', 'household_no', 'hh_number', 'household_number', 'house_no', 'household_label'],
        'last_name' => ['last_name', 'lastname', 'surname'],
        'first_name' => ['first_name', 'firstname', 'given_name'],
        'middle_name' => ['middle_name', 'midle_name', 'middlename'],
        'ext' => ['ext', 'suffix', 'suffix_name'],
        'purok_sitio' => ['purok_street_name_name_of_subdivision_zone', 'purok_street_name_name_of_subdivision_n_zone', 'purok_sitio', 'purok', 'street_name', 'address', 'zone'],
        'date_of_birth' => ['date_of_birth', 'birthdate'],
        'place_of_birth' => ['place_of_birth'],
        'age_bracket' => ['age_bracket', 'age'],
        'civil_status' => ['civil_status'],
        'sex' => ['sex', 'gender'],
        'weight' => ['weight', 'weight_kg'],
        'height' => ['height', 'height_cm'],
        'educational_attainment' => ['educational_attainment', 'education_level', 'education'],
        'citizenship' => ['citizenship'],
        'language_spoken' => ['language_spoken'],
        'religious_affiliation' => ['religious_affiliation', 'religion'],
        'occupation' => ['occupation'],
        'employment_status' => ['employment_status', 'employment_status_a_regular_b_contractual', 'a_regular_b_contractual'],
        'ofw_details' => ['ofw_no_years_working_location', 'ofw_details'],
        'current_skill' => ['current_skill', 'employment_status_current_skill'],
        'desired_skill' => ['additional_skill_to_acquire', 'desired_skill', 'employment_status_additional_skill_to_acquire'],
        'unemployed_current_skill' => ['skill_of_un_employed_member_current_skill', 'unemployed_current_skill'],
        'unemployed_desired_skill' => ['skill_of_un_employed_member_additional_skill_to_acquire', 'unemployed_desired_skill'],
        'average_monthly_income' => ['average_monthly_income'],
        'emerging_diseases' => ['emerging_diseases'],
        'disability' => ['disability'],
        'relationship_to_head' => ['relation_to_hh_heads', 'relation_to_hh_heads', 'relationship_to_head', 'relationship'],
        'remarks' => ['remarks', 'notes'],
        'barangay' => ['barangay', 'barangay_name'],
        'member_name' => ['member_name', 'full_name', 'name'],
        'housing_type' => ['housing_type', 'type_of_building', 'building_type'],
        'tenure_status' => ['tenure_status', 'house_tenure', 'housing_tenure'],
        'water_source' => ['water_source', 'main_source_of_water_supply', 'source_of_water_supply'],
        'toilet_type' => ['toilet_type', 'kind_of_toilet_facility', 'toilet_facility'],
        'electricity_source' => ['electricity_source', 'source_of_lighting', 'fuel_energy_source_for_lighting'],
        'waste_disposal_method' => ['waste_disposal_method', 'garbage_disposal', 'solid_waste_disposal'],
        'main_livelihood' => ['main_livelihood', 'primary_income_source', 'income_source', 'livelihood'],
        'monthly_household_income' => ['monthly_household_income', 'monthly_income', 'household_income'],
        'monthly_income_band' => ['monthly_income_band', 'income_band'],
        'farming_household' => ['farming_household', 'farming_related', 'farmer_household'],
        'farm_area_hectares' => ['farm_area_hectares', 'farm_area', 'farm_size_hectares'],
        'fruit_tree_count_estimate' => ['fruit_tree_count_estimate', 'fruit_tree_count', 'estimated_fruit_tree_count'],
        'special_program_notes' => ['special_program_notes', 'program_notes', 'special_notes'],
        'is_4ps' => ['is_4ps', 'pantawid_4ps', '4ps_beneficiary'],
        'has_senior' => ['has_senior', 'with_senior', 'senior_citizen_present'],
        'has_pwd' => ['has_pwd', 'with_pwd', 'pwd_present'],
        'has_solo_parent' => ['has_solo_parent', 'solo_parent_present'],
        'has_pregnant_member' => ['has_pregnant_member', 'pregnant_member_present'],
        'has_philhealth' => ['has_philhealth', 'philhealth'],
        'receives_lgu_assistance' => ['receives_lgu_assistance', 'lgu_assistance', 'receiving_lgu_assistance'],
        'priority_level' => ['priority_level', 'priority', 'special_program_priority'],
        'priority_notes' => ['priority_notes', 'priority_reason'],
    ];
}

function import_row_value(array $row, array $aliases, string $default = ''): string {
    foreach ($aliases as $alias) {
        $key = import_normalize_header($alias);
        if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    }
    return $default;
}


function import_row_hh_no(array $row, string $default = ''): string {
    foreach (['official_hh_no', 'source_hh_no', 'registered_hh_no', 'hh_no'] as $alias) {
        $value = trim(import_row_value($row, [$alias]));
        if ($value !== '') return $value;
    }
    $keys = array_keys($row);
    usort($keys, static function ($a, $b) {
        return strlen((string)$a) <=> strlen((string)$b);
    });
    foreach ($keys as $key) {
        if (!preg_match('/^hh_no_*$/', (string)$key)) continue;
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return $default;
}

function import_apply_aliases(array $row): array {
    $mapped = $row;
    foreach (import_alias_map() as $target => $aliases) {
        if (!isset($mapped[$target]) || trim((string)$mapped[$target]) === '') {
            foreach ($aliases as $alias) {
                $key = import_normalize_header($alias);
                if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                    $mapped[$target] = trim((string)$row[$key]);
                    break;
                }
            }
        }
    }
    return $mapped;
}

function import_excel_col_to_index(string $letters): int {
    $letters = strtoupper($letters);
    $n = 0;
    for ($i = 0; $i < strlen($letters); $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
    return $n;
}

function import_extract_sheet_title_simplexml($sheet): string {
    $attrs = $sheet->attributes('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    if (isset($attrs['name'])) return (string)$attrs['name'];
    $attrs = $sheet->attributes();
    return (string)($attrs['name'] ?? '');
}

function import_read_xlsx_file(string $path): array {
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    $sharedStrings = [];
    $shared = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared !== false) {
        $xml = @simplexml_load_string($shared);
        if ($xml) {
            foreach ($xml->si as $si) {
                $value = '';
                if (isset($si->t)) $value .= (string)$si->t;
                if (isset($si->r)) foreach ($si->r as $run) $value .= (string)$run->t;
                $sharedStrings[] = trim($value);
            }
        }
    }

    $relsMap = [];
    $relsXmlText = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($relsXmlText !== false) {
        $relsXml = @simplexml_load_string($relsXmlText);
        if ($relsXml) {
            foreach ($relsXml->Relationship as $rel) {
                $id = (string)$rel['Id'];
                $target = (string)$rel['Target'];
                if ($id !== '' && $target !== '') $relsMap[$id] = 'xl/' . ltrim($target, '/');
            }
        }
    }

    $sheets = [];
    $workbook = @simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    if (!$workbook || !isset($workbook->sheets)) { $zip->close(); return []; }

    foreach ($workbook->sheets->sheet as $sheetNode) {
        $sheetName = import_extract_sheet_title_simplexml($sheetNode);
        $attrs = $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string)($attrs['id'] ?? '');
        if ($rid === '' || empty($relsMap[$rid])) continue;
        $sheetPath = $relsMap[$rid];
        $sheetXmlText = $zip->getFromName($sheetPath);
        if ($sheetXmlText === false) continue;
        $sheetXml = @simplexml_load_string($sheetXmlText);
        if (!$sheetXml || !isset($sheetXml->sheetData)) continue;

        $rows = [];
        foreach ($sheetXml->sheetData->row as $rowNode) {
            $rowNum = (int)($rowNode['r'] ?? 0);
            $row = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string)$cell['r'];
                if (!preg_match('/([A-Z]+)(\d+)/', $ref, $m)) continue;
                $col = import_excel_col_to_index($m[1]);
                $type = (string)($cell['t'] ?? '');
                $value = '';
                if ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string)$cell->is->t;
                } elseif ($type === 's') {
                    $idx = (int)($cell->v ?? -1);
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'b') {
                    $value = ((string)($cell->v ?? '') === '1') ? '1' : '0';
                } else {
                    $value = isset($cell->v) ? (string)$cell->v : '';
                }
                $row[$col] = trim((string)$value);
            }
            if ($row) $rows[$rowNum] = $row;
        }
        ksort($rows);
        $sheets[] = ['name' => $sheetName, 'rows' => $rows];
    }

    $zip->close();
    return $sheets;
}

function import_read_csv_file(array $file): array {
    $rows = [];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return [];
    $fh = fopen($file['tmp_name'], 'r');
    if (!$fh) return [];
    $headers = fgetcsv($fh);
    if (!$headers) { fclose($fh); return []; }
    $headers = array_map('import_normalize_header', $headers);
    while (($data = fgetcsv($fh)) !== false) {
        if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) continue;
        $row = [];
        foreach ($headers as $i => $h) $row[$h] = trim((string)($data[$i] ?? ''));
        $rows[] = import_apply_aliases($row);
    }
    fclose($fh);
    return [[
        'name' => pathinfo($file['name'] ?? 'CSV', PATHINFO_FILENAME) ?: 'CSV',
        'barangay' => '',
        'rows' => $rows,
    ]];
}

function import_barangay_match_key(string $name): string {
    $name = trim($name);
    if ($name === '') return '';
    $name = preg_replace('/\b20\d{2}\b/u', '', $name);
    $name = str_ireplace(['barangay:', 'barangay'], '', $name);
    $name = str_ireplace(['brgy.', 'brgy', 'bgy.', 'bgy'], '', $name);
    $name = preg_replace('/[()]/', ' ', $name);
    $name = str_ireplace(['poblacion'], ' poblacion ', $name);
    $name = str_ireplace(['sta.', 'sta '], 'santa ', $name);
    $name = preg_replace('/\bsan dionesio\b/i', 'san dionisio', $name);
    $name = preg_replace('/[^a-z0-9]+/i', ' ', $name);
    $name = preg_replace('/\s+/', ' ', strtolower(trim($name)));
    return $name;
}

function import_barangay_aliases(): array {
    return [
        'balagtas' => 'Balagtas',
        'bonoy' => 'Bonoy',
        'bulak' => 'Bulak',
        'cambadbad' => 'Cambadbad',
        'candelaria' => 'Candelaria',
        'cansoso' => 'Cansoso',
        'imelda' => 'Imelda',
        'malazarte' => 'Malazarte',
        'mansaha on' => 'Mansaha-on',
        'mansahaon' => 'Mansaha-on',
        'mansalip' => 'Mansalip',
        'masaba' => 'Masaba',
        'naulayan' => 'Naulayan',
        'riverside' => 'Riverside (Poblacion)',
        'riverside poblacion' => 'Riverside (Poblacion)',
        'san dionisio' => 'San Dionisio',
        'san dionesio' => 'San Dionisio',
        'san guillermo' => 'San Guillermo (Poblacion)',
        'san guillermo poblacion' => 'San Guillermo (Poblacion)',
        'san marcelino' => 'San Marcelino',
        'san sebastian' => 'San Sebastian',
        'san vicente' => 'San Vicente',
        'santa rosa' => 'Santa Rosa',
        'sta rosa' => 'Santa Rosa',
        'sto rosario' => 'Santo Rosario',
        'santo rosario' => 'Santo Rosario',
        'talisay' => 'Talisay (Poblacion)',
        'talisay poblacion' => 'Talisay (Poblacion)',
    ];
}

function import_clean_barangay_name(string $name): string {
    $original = trim((string)$name);
    if ($original === '') return '';

    $aliases = import_barangay_aliases();
    $variants = [];
    $variants[] = $original;

    $clean = preg_replace('/\b20\d{2}\b/u', '', $original);
    $clean = preg_replace('/^\s*barangay:?\s*/iu', '', $clean);
    $clean = preg_replace('/^\s*brgy\.?\s*/iu', '', $clean);
    $clean = preg_replace('/\s+/', ' ', trim((string)$clean));
    if ($clean !== '') $variants[] = $clean;

    foreach ($variants as $variant) {
        $key = import_barangay_match_key($variant);
        if (isset($aliases[$key])) return $aliases[$key];
    }

    $cleanKey = import_barangay_match_key($clean !== '' ? $clean : $original);
    foreach ($aliases as $aliasKey => $aliasValue) {
        if ($cleanKey === $aliasKey || str_contains($aliasKey, $cleanKey) || str_contains($cleanKey, $aliasKey)) {
            return $aliasValue;
        }
    }

    return $clean !== '' ? $clean : $original;
}

function import_is_generic_sheet_name(string $sheetName): bool {
    $key = import_normalize_header($sheetName);
    if ($key === '') return true;
    return (bool)preg_match('/^(sheet|worksheet|tab|page)_?[0-9]*$/i', $key);
}

function import_row_barangay_name(array $row, string $sheetBarangay = '', string $sheetName = ''): string {
    $preferredSheet = import_clean_barangay_name($sheetName);
    if ($preferredSheet !== '' && !import_is_generic_sheet_name($sheetName)) return $preferredSheet;

    foreach ([$sheetBarangay, import_row_value($row, ['barangay']), $sheetName] as $candidate) {
        $clean = import_clean_barangay_name((string)$candidate);
        if ($clean !== '') return $clean;
    }
    return '';
}

function import_sheet_barangay(string $sheetName, array $sheetRows): string {
    $preferredSheet = import_clean_barangay_name($sheetName);
    if ($preferredSheet !== '' && !import_is_generic_sheet_name($sheetName)) return $preferredSheet;

    foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $r) {
        $row = $sheetRows[$r] ?? [];
        if (!$row) continue;

        foreach ($row as $index => $value) {
            $cell = trim((string)$value);
            if ($cell === '') continue;

            if (preg_match('/^\s*(barangay|brgy\.?)\s*:?\s*(.+)$/iu', $cell, $m)) {
                $parsed = trim((string)$m[2]);
                if ($parsed !== '') return import_clean_barangay_name($parsed);
            }

            if (strcasecmp($cell, 'Barangay:') === 0 || strcasecmp($cell, 'Barangay') === 0 || strcasecmp($cell, 'Brgy.') === 0 || strcasecmp($cell, 'Brgy') === 0) {
                $next = trim((string)($row[$index + 1] ?? ''));
                if ($next !== '') return import_clean_barangay_name($next);
            }
        }
    }
    return $preferredSheet;
}

function import_is_data_sheet(string $sheetName, array $sheetRows): bool {

    $upper = strtoupper(trim($sheetName));
    if (in_array($upper, ['0', 'STAT', 'SHEET3', 'POPULATION PROFILE'], true)) return false;
    foreach (range(1, 10) as $r) {
        $row = $sheetRows[$r] ?? [];
        $flat = [];
        foreach ($row as $v) $flat[] = import_normalize_header((string)$v);
        $joined = implode('|', $flat);
        if (str_contains($joined, 'last_name') && str_contains($joined, 'first_name')) return true;
        if (str_contains($joined, 'relation_to_hh_heads')) return true;
    }
    return false;
}

function import_build_sheet_rows(array $sheet): array {
    $sheetRows = $sheet['rows'] ?? [];
    if (!$sheetRows) return [];
    $headerRow = 0;
    foreach (range(1, 10) as $r) {
        $row = $sheetRows[$r] ?? [];
        $values = [];
        foreach ($row as $v) $values[] = import_normalize_header((string)$v);
        if (in_array('last_name', $values, true) && in_array('first_name', $values, true)) { $headerRow = $r; break; }
    }
    if ($headerRow <= 0) return [];

    $subHeaderRow = $headerRow + 1;
    $main = $sheetRows[$headerRow] ?? [];
    $sub = $sheetRows[$subHeaderRow] ?? [];
    $maxCol = 0;
    foreach ([$main, $sub] as $source) foreach (array_keys($source) as $c) $maxCol = max($maxCol, (int)$c);

    $headers = [];
    for ($c = 1; $c <= $maxCol; $c++) {
        $h1 = trim((string)($main[$c] ?? ''));
        $h2 = trim((string)($sub[$c] ?? ''));
        $combined = $h1;
        if ($h2 !== '' && strcasecmp($h2, $h1) !== 0) $combined = trim($h1 . ' ' . $h2);
        $headers[$c] = import_normalize_header($combined);
    }

    $barangay = import_sheet_barangay($sheet['name'] ?? '', $sheetRows);
    $rows = [];
    $dataStartRow = $subHeaderRow + 1;
    $probe = $sheetRows[$dataStartRow] ?? [];
    $probeHasHeaderWords = false;
    foreach ($probe as $v) {
        $token = import_normalize_header((string)$v);
        if (in_array($token, ['last_name','first_name','midle_name','middle_name','purok_street_name_name_of_subdivision_zone','relation_to_hh_heads'], true)) {
            $probeHasHeaderWords = true;
            break;
        }
    }
    if ($probeHasHeaderWords) $dataStartRow++;
    for ($r = $dataStartRow; $r <= max(array_keys($sheetRows)); $r++) {
        $source = $sheetRows[$r] ?? [];
        if (!$source) continue;
        $mapped = [];
        $hasData = false;
        foreach ($headers as $c => $key) {
            if ($key === '') continue;
            $value = trim((string)($source[$c] ?? ''));
            if ($value !== '') $hasData = true;
            $mapped[$key] = $value;
        }
        if (!$hasData) {
            $rows[] = ['__blank' => true, 'barangay' => $barangay];
            continue;
        }
        $mapped['barangay'] = $barangay;
        $mapped['sheet_barangay'] = $barangay;
        $mapped['sheet_name'] = $sheet['name'] ?? '';
        $mapped['__rownum'] = (string)$r;
        $rows[] = import_apply_aliases($mapped);
    }
    return $rows;
}

function import_read_uploaded_rows(array $file, string &$sourceKind = ''): array {
    $name = strtolower((string)($file['name'] ?? ''));
    if (str_ends_with($name, '.xlsx')) {
        $sourceKind = 'xlsx';
        $sheets = import_read_xlsx_file($file['tmp_name']);
        $all = [];
        foreach ($sheets as $sheet) {
            if (!import_is_data_sheet($sheet['name'] ?? '', $sheet['rows'] ?? [])) continue;
            $sheetRows = import_build_sheet_rows($sheet);
            if ($sheetRows) $all[] = ['name' => $sheet['name'], 'barangay' => import_sheet_barangay($sheet['name'], $sheet['rows']), 'rows' => $sheetRows];
        }
        return $all;
    }
    if (str_ends_with($name, '.csv')) {
        $sourceKind = 'csv';
        return import_read_csv_file($file);
    }
    return [];
}

function import_yesno(string $value): int {
    $v = strtolower(trim($value));
    return in_array($v, ['1','yes','y','true','checked','x'], true) ? 1 : 0;
}

function import_find_barangay_id(mysqli $conn, string $name): int {
    $name = import_clean_barangay_name($name);
    if ($name === '') return 0;

    $candidates = [$name];
    $needle = import_barangay_match_key($name);
    $aliases = import_barangay_aliases();
    if (isset($aliases[$needle])) $candidates[] = $aliases[$needle];
    foreach ($aliases as $aliasKey => $aliasValue) {
        if ($aliasKey === $needle || str_contains($aliasKey, $needle) || str_contains($needle, $aliasKey)) {
            $candidates[] = $aliasValue;
        }
    }
    $candidates = array_values(array_unique(array_filter($candidates, fn($v) => trim((string)$v) !== '')));

    foreach ($candidates as $candidate) {
        $stmt = $conn->prepare("SELECT barangay_id FROM barangays WHERE LOWER(TRIM(barangay_name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res) return (int)$res['barangay_id'];
        }
    }

    $query = $conn->query("SELECT barangay_id, barangay_name FROM barangays");
    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $dbName = (string)($row['barangay_name'] ?? '');
            $dbKey = import_barangay_match_key($dbName);
            foreach ($candidates as $candidate) {
                $candidateKey = import_barangay_match_key($candidate);
                if ($dbKey === $candidateKey || str_contains($dbKey, $candidateKey) || str_contains($candidateKey, $dbKey)) {
                    $query->close();
                    return (int)$row['barangay_id'];
                }
            }
        }
        $query->close();
    }
    return 0;
}

function import_parse_date($value): ?string {
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
    $value = trim((string)$value);
    if ($value === '') return null;
    if (is_numeric($value)) {
        $serial = (float)$value;
        if ($serial > 59 && $serial < 60000) {
            $base = new DateTime('1899-12-30');
            $base->modify('+' . ((int)floor($serial)) . ' days');
            return $base->format('Y-m-d');
        }
    }
    $formats = ['Y-m-d','m/d/Y','n/j/Y','d/m/Y','m-d-Y','d-m-Y','Y/m/d','n/j/y','m/d/y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) return $dt->format('Y-m-d');
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : null;
}

function import_parse_int($value): ?int {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = str_replace(',', '', $value);
    if (!is_numeric($value)) return null;
    return (int)round((float)$value);
}

function import_parse_decimal($value, int $scale = 2): ?float {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = str_replace(',', '', $value);
    if (!is_numeric($value)) return null;
    return round((float)$value, $scale);
}


function import_guess_income_band(?float $income): ?string {
    if ($income === null) return null;
    if ($income <= 5000) return 'Below 5,000';
    if ($income <= 10000) return '5,001 - 10,000';
    if ($income <= 20000) return '10,001 - 20,000';
    if ($income <= 50000) return '20,001 - 50,000';
    return 'Above 50,000';
}

function import_normalize_priority_level(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $map = [
        'routine' => 'Routine',
        'watchlist' => 'Watchlist',
        'priority' => 'Priority',
        'high priority' => 'High Priority',
        'high' => 'High Priority',
        'urgent' => 'High Priority',
        'medium' => 'Priority',
        'low' => 'Watchlist',
    ];
    $key = strtolower(preg_replace('/\s+/', ' ', $value));
    return $map[$key] ?? import_title_value($value);
}

function import_upsert_cbms_lite(mysqli $conn, int $householdId, array $row, int $uid): void {
    if ($householdId <= 0) return;
    $monthlyIncome = import_parse_decimal(import_row_value($row, ['monthly_household_income', 'monthly_income', 'household_income']), 2);
    $incomeBand = import_row_value($row, ['monthly_income_band', 'income_band']);
    if ($incomeBand === '') $incomeBand = import_guess_income_band($monthlyIncome) ?? '';
    $mainLivelihood = import_row_value($row, ['main_livelihood', 'primary_income_source', 'income_source', 'livelihood']);
    $cropSummary = import_row_value($row, ['crop_summary', 'crop_name']);
    $cbmsPayload = [
        'housing_type' => import_row_value($row, ['housing_type', 'type_of_building', 'building_type']) ?: null,
        'tenure_status' => import_row_value($row, ['tenure_status', 'house_tenure', 'housing_tenure']) ?: null,
        'water_source' => import_row_value($row, ['water_source', 'main_source_of_water_supply', 'source_of_water_supply']) ?: null,
        'toilet_type' => import_row_value($row, ['toilet_type', 'kind_of_toilet_facility', 'toilet_facility']) ?: null,
        'electricity_source' => import_row_value($row, ['electricity_source', 'source_of_lighting', 'fuel_energy_source_for_lighting']) ?: null,
        'waste_disposal_method' => import_row_value($row, ['waste_disposal_method', 'garbage_disposal', 'solid_waste_disposal']) ?: null,
        'monthly_household_income' => $monthlyIncome,
        'livelihood_summary' => $mainLivelihood ?: null,
        'crop_summary' => $cropSummary ?: null,
        'farming_household' => import_yesno(import_row_value($row, ['farming_household', 'farming_related', 'farmer_household'], ($cropSummary !== '' || stripos($mainLivelihood, 'farm') !== false) ? 'yes' : 'no')),
        'farm_area_hectares' => import_parse_decimal(import_row_value($row, ['farm_area_hectares', 'farm_area', 'farm_size_hectares']), 2),
        'fruit_tree_count_estimate' => import_parse_int(import_row_value($row, ['fruit_tree_count_estimate', 'fruit_tree_count', 'estimated_fruit_tree_count'])),
        'special_program_notes' => import_row_value($row, ['special_program_notes', 'program_notes', 'special_notes']) ?: null,
        'poverty_status' => import_normalize_priority_level(import_row_value($row, ['priority_level', 'priority', 'special_program_priority'])) ?: null,
        'notes' => import_row_value($row, ['remarks', 'notes']) ?: null,
    ];
    if (function_exists('save_cbms_profile_row') && array_filter($cbmsPayload, fn($v) => $v !== null && $v !== '' && $v != 0)) {
        save_cbms_profile_row($conn, 'cbms_household_profiles', $householdId, $cbmsPayload, $uid);
    }
    $flagsPayload = [
        'is_4ps' => import_yesno(import_row_value($row, ['is_4ps', 'pantawid_4ps', '4ps_beneficiary'])),
        'has_senior' => import_yesno(import_row_value($row, ['has_senior', 'with_senior', 'senior_citizen_present'])),
        'has_pwd' => import_yesno(import_row_value($row, ['has_pwd', 'with_pwd', 'pwd_present'])),
        'has_solo_parent' => import_yesno(import_row_value($row, ['has_solo_parent', 'solo_parent_present'])),
        'has_pregnant_member' => import_yesno(import_row_value($row, ['has_pregnant_member', 'pregnant_member_present'])),
        'has_philhealth' => import_yesno(import_row_value($row, ['has_philhealth', 'philhealth'])),
        'receives_lgu_assistance' => import_yesno(import_row_value($row, ['receives_lgu_assistance', 'lgu_assistance', 'receiving_lgu_assistance'])),
        'priority_level' => import_normalize_priority_level(import_row_value($row, ['priority_level', 'priority', 'special_program_priority'])),
        'priority_notes' => import_row_value($row, ['priority_notes', 'priority_reason']) ?: null,
    ];
    if (function_exists('save_household_beneficiary_flags') && array_filter($flagsPayload, fn($v) => $v !== null && $v !== '' && $v != 0)) {
        save_household_beneficiary_flags($conn, $householdId, $flagsPayload, $uid);
    }
    if (function_exists('save_cbms_profile_row') && table_exists($conn, 'cbms_livelihood_profiles') && ($mainLivelihood !== '' || $incomeBand !== '')) {
        save_cbms_profile_row($conn, 'cbms_livelihood_profiles', $householdId, [
            'primary_income_source' => $mainLivelihood ?: null,
            'main_livelihood' => $mainLivelihood ?: null,
            'monthly_income_band' => $incomeBand ?: null,
        ], $uid);
    }
    if (function_exists('save_cbms_profile_row') && table_exists($conn, 'cbms_housing_profiles')) {
        $housingPayload = [
            'housing_type' => $cbmsPayload['housing_type'],
            'tenure_status' => $cbmsPayload['tenure_status'],
            'electricity_source' => $cbmsPayload['electricity_source'],
        ];
        if (array_filter($housingPayload, fn($v) => $v !== null && $v !== '')) {
            save_cbms_profile_row($conn, 'cbms_housing_profiles', $householdId, $housingPayload, $uid);
        }
    }
    if (function_exists('save_cbms_profile_row') && table_exists($conn, 'cbms_sanitation_profiles')) {
        $sanitationPayload = [
            'water_source' => $cbmsPayload['water_source'],
            'toilet_type' => $cbmsPayload['toilet_type'],
            'waste_disposal' => $cbmsPayload['waste_disposal_method'],
        ];
        if (array_filter($sanitationPayload, fn($v) => $v !== null && $v !== '')) {
            save_cbms_profile_row($conn, 'cbms_sanitation_profiles', $householdId, $sanitationPayload, $uid);
        }
    }
}

function import_title_value(string $value): string {
    $value = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $value)));
    return $value === '' ? '' : ucwords(strtolower($value));
}

function import_normalize_enum(string $value, array $options, string $fallback = ''): string {
    $value = trim($value);
    if ($value === '') return $fallback;
    foreach ($options as $opt) {
        if (strcasecmp($opt, $value) === 0) return $opt;
    }
    $normalized = import_title_value($value);
    foreach ($options as $opt) {
        if (strcasecmp($opt, $normalized) === 0) return $opt;
    }
    return $fallback !== '' ? $fallback : $value;
}

function import_compose_full_name(array $row): string {
    $full = import_row_value($row, ['member_name', 'full_name', 'name']);
    if ($full !== '') return preg_replace('/\s+/', ' ', $full);
    $parts = [
        import_row_value($row, ['first_name']),
        import_row_value($row, ['middle_name']),
        import_row_value($row, ['last_name']),
        import_row_value($row, ['ext']),
    ];
    $parts = array_values(array_filter(array_map(fn($v) => trim((string)$v), $parts), fn($v) => $v !== ''));
    return preg_replace('/\s+/', ' ', implode(' ', $parts));
}

function import_relationship_label(array $row): string {
    $raw = trim(import_row_value($row, ['relationship_to_head', 'relation_to_hh_heads', 'relationship']));
    if ($raw === '') return '';
    $normalized = strtolower(preg_replace('/\s+/', ' ', $raw));
    if (str_contains($normalized, 'hh head') || $normalized === 'head' || str_contains($normalized, 'household head')) return 'Head';
    return import_title_value($raw);
}

function import_is_head_row(array $row): bool {
    if (import_yesno(import_row_value($row, ['is_household_head', 'is_head'])) === 1) return true;
    return strcasecmp(import_relationship_label($row), 'Head') === 0;
}

function import_is_letter_hh_marker(string $marker): bool {
    return (bool)preg_match('/^[A-Za-z]+$/', trim($marker));
}

function import_letter_marker_index(string $marker): ?int {
    $marker = strtoupper(trim($marker));
    if ($marker === '' || !preg_match('/^[A-Z]+$/', $marker)) return null;
    $value = 0;
    $len = strlen($marker);
    for ($i = 0; $i < $len; $i++) {
        $value = ($value * 26) + (ord($marker[$i]) - 64);
    }
    return $value > 0 ? $value : null;
}

function import_next_cluster_key(string $sheetName, string $barangay, int $sequence): string {
    $sheetToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $sheetName));
    $barangayToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $barangay));
    $base = $barangayToken !== '' ? $barangayToken : ($sheetToken !== '' ? $sheetToken : 'SHEET');
    return 'CL-' . $base . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
}

function import_sheet_groups(array $sheetBlock, array &$results): array {
    $groups = [];
    $currentRows = [];
    $currentMarker = '';
    $groupSequence = 0;
    $clusterSequence = 0;
    $activeCluster = null;
    $previousLetterIndex = null;
    $sheetName = (string)($sheetBlock['name'] ?? '');
    $sheetBarangay = import_clean_barangay_name((string)($sheetBlock['barangay'] ?? ''));

    $finalizeGroup = function () use (&$groups, &$currentRows, &$currentMarker, &$groupSequence, &$clusterSequence, &$activeCluster, &$previousLetterIndex, $sheetName, $sheetBarangay) {
        if (!$currentRows) return;
        $groupSequence++;
        $marker = trim((string)$currentMarker);
        $barangay = import_clean_barangay_name(import_row_value($currentRows[0] ?? [], ['barangay'], $sheetBarangay));
        $clusterKey = null;
        $blockLabel = null;

        if (import_is_letter_hh_marker($marker)) {
            $markerIndex = import_letter_marker_index($marker);
            if ($activeCluster === null || $previousLetterIndex === null || $markerIndex === null || $markerIndex !== ($previousLetterIndex + 1)) {
                $clusterSequence++;
                $activeCluster = import_next_cluster_key($sheetName, $barangay, $clusterSequence);
            }
            $clusterKey = $activeCluster;
            $blockLabel = strtoupper($marker);
            $previousLetterIndex = $markerIndex;
        } else {
            $activeCluster = null;
            $previousLetterIndex = null;
        }

        foreach ($currentRows as &$groupRow) {
            if (!is_array($groupRow)) continue;
            $groupRow['__group_sequence'] = (string)$groupSequence;
            $groupRow['__group_marker'] = $marker;
            $groupRow['__cluster_key'] = $clusterKey;
            $groupRow['__block_label'] = $blockLabel;
            $groupRow['__sheet_name'] = $sheetName;
        }
        unset($groupRow);

        $groups[] = $currentRows;
        $currentRows = [];
        $currentMarker = '';
    };

    foreach (($sheetBlock['rows'] ?? []) as $row) {
        if (!is_array($row)) continue;
        if (!empty($row['__blank'])) {
            $finalizeGroup();
            continue;
        }

        $memberName = import_compose_full_name($row);
        if ($memberName === '') continue;

        $marker = trim(import_row_hh_no($row));
        $startNew = false;
        if (!$currentRows) {
            $startNew = true;
        } else {
            $isHead = import_is_head_row($row);
            if ($marker !== '' && $currentMarker !== '' && strcasecmp($marker, $currentMarker) !== 0) {
                $startNew = true;
            } elseif ($marker !== '' && $currentMarker === '') {
                $startNew = true;
            } elseif ($isHead) {
                // In the RBI workbook, a new Head row starts a new family unit even inside the same household block.
                $startNew = true;
            }
        }

        if ($startNew && $currentRows) {
            $finalizeGroup();
        }

        if (!$currentRows) {
            $currentMarker = $marker;
        } elseif ($currentMarker === '' && $marker !== '') {
            $currentMarker = $marker;
        }

        $currentRows[] = $row;
    }

    $finalizeGroup();
    return $groups;
}

function import_member_lookup(mysqli $conn, int $householdId, string $fullName, ?string $birthdate, string $relationship): ?array {
    $fullNameSafe = $conn->real_escape_string($fullName);
    $sql = "SELECT member_id FROM family_members WHERE household_id={$householdId} AND full_name='{$fullNameSafe}'";
    if ($birthdate) {
        $birthSafe = $conn->real_escape_string($birthdate);
        $sql .= " AND (birthdate='{$birthSafe}' OR birthdate IS NULL)";
    }
    $sql .= " ORDER BY is_household_head DESC, member_id DESC LIMIT 1";
    $row = fetch_one($conn, $sql);
    if ($row) return $row;
    $relSafe = $conn->real_escape_string($relationship);
    return fetch_one($conn, "SELECT member_id FROM family_members WHERE household_id={$householdId} AND full_name='{$fullNameSafe}' AND relationship_to_head='{$relSafe}' ORDER BY member_id DESC LIMIT 1");
}

function import_household_lookup(mysqli $conn, array $row): ?array {
    $sourceFamilyKey = import_row_value($row, ['source_family_key']);
    if ($sourceFamilyKey !== '' && column_exists($conn, 'households', 'source_family_key')) {
        $found = fetch_one($conn, "SELECT household_id FROM households WHERE source_family_key='" . $conn->real_escape_string($sourceFamilyKey) . "' LIMIT 1");
        if ($found) return $found;
    }
    $reference = import_row_value($row, ['reference_no']);
    $householdCode = import_row_value($row, ['household_code']);
    $registered = import_row_value($row, ['registered_hh_no']);
    $head = import_row_value($row, ['household_head_name', 'member_name']);
    $resolvedBarangay = import_row_barangay_name($row, import_row_value($row, ['sheet_barangay']), import_row_value($row, ['sheet_name']));
    $barangayId = import_find_barangay_id($conn, $resolvedBarangay);

    if ($reference !== '' && column_exists($conn, 'households', 'reference_no')) {
        $found = fetch_one($conn, "SELECT household_id FROM households WHERE reference_no='" . $conn->real_escape_string($reference) . "' LIMIT 1");
        if ($found) return $found;
    }
    if ($householdCode !== '' && column_exists($conn, 'households', 'household_code')) {
        $found = fetch_one($conn, "SELECT household_id FROM households WHERE household_code='" . $conn->real_escape_string($householdCode) . "' LIMIT 1");
        if ($found) return $found;
    }
    $sourceHh = import_row_value($row, ['source_hh_no', 'registered_hh_no']);
    $sourceSheet = import_row_value($row, ['source_sheet_name', 'sheet_name']);
    if ($sourceHh !== '' && column_exists($conn, 'households', 'source_hh_no')) {
        $sql = "SELECT household_id FROM households WHERE source_hh_no='" . $conn->real_escape_string($sourceHh) . "'";
        if ($barangayId > 0) $sql .= " AND barangay_id={$barangayId}";
        if ($sourceSheet !== '' && column_exists($conn, 'households', 'source_sheet_name')) $sql .= " AND source_sheet_name='" . $conn->real_escape_string($sourceSheet) . "'";
        if ($head !== '') $sql .= " AND household_head_name='" . $conn->real_escape_string($head) . "'";
        $sql .= " LIMIT 1";
        $found = fetch_one($conn, $sql);
        if ($found) return $found;
    }
    if ($registered !== '' && column_exists($conn, 'households', 'registered_hh_no')) {
        $sql = "SELECT household_id FROM households WHERE registered_hh_no='" . $conn->real_escape_string($registered) . "'";
        if ($barangayId > 0) $sql .= " AND barangay_id={$barangayId}";
        if ($head !== '') $sql .= " AND household_head_name='" . $conn->real_escape_string($head) . "'";
        $sql .= " LIMIT 1";
        $found = fetch_one($conn, $sql);
        if ($found) return $found;
    }
    return null;
}


function import_preview_summary(mysqli $conn, array $sheetBlocks, string $type): array {
    $report = [
        'type' => $type,
        'rows_read' => 0,
        'sheet_count' => count($sheetBlocks),
        'sheet_names' => [],
        'families_detected' => 0,
        'households_detected' => 0,
        'members_detected' => 0,
        'skipped_rows' => 0,
        'duplicate_like_rows' => 0,
        'warnings' => [],
        'barangays' => [],
    ];
    $seenMembers = [];
    $seenFamilyKeys = [];
    $seenHouseholdKeys = [];
    $hhCounts = [];
    foreach ($sheetBlocks as $sheetBlock) {
        $sheetName = (string)($sheetBlock['name'] ?? 'Unknown');
        $report['sheet_names'][] = $sheetName;
        $tmpResults = ['issues' => []];
        if ($type === 'profiling') {
            $groups = import_sheet_groups($sheetBlock, $tmpResults);
            foreach (($sheetBlock['rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                if (!empty($row['__blank'])) { $report['skipped_rows']++; continue; }
                $name = trim(import_compose_full_name($row));
                if ($name === '') { $report['skipped_rows']++; continue; }
                $report['rows_read']++;
                $report['members_detected']++;
                $barangay = import_row_barangay_name($row, (string)($sheetBlock['barangay'] ?? ''), $sheetName) ?: 'Unknown barangay';
                $report['barangays'][$barangay] = ($report['barangays'][$barangay] ?? 0) + 1;
                $sig = strtolower($barangay . '|' . $name . '|' . (trim((string)import_row_value($row, ['date_of_birth']))));
                if (isset($seenMembers[$sig])) $report['duplicate_like_rows']++;
                $seenMembers[$sig] = true;
                $hhNo = trim(import_row_hh_no($row));
                if ($hhNo !== '') $hhCounts[strtoupper($hhNo)] = ($hhCounts[strtoupper($hhNo)] ?? 0) + 1;
            }
            $sequence = 0;
            foreach ($groups as $groupRows) {
                $groupRows = import_normalize_group_rows($groupRows);
                if (!$groupRows) continue;
                $sequence++;
                $first = import_group_household_row($groupRows, $sheetName, $sequence);
                $report['families_detected']++;
                $familyKey = (string)($first['source_family_key'] ?? ('FAM|' . $sequence));
                $seenFamilyKeys[$familyKey] = true;
                $householdKey = (string)($first['household_group_key'] ?? $familyKey);
                $seenHouseholdKeys[$householdKey] = true;
            }
        } else {
            foreach (($sheetBlock['rows'] ?? []) as $row) {
                if (!is_array($row) || !empty($row['__blank'])) { $report['skipped_rows']++; continue; }
                $report['rows_read']++;
                $hh = trim(import_row_value($row, ['household_code', 'reference_no', 'household_name', 'name']));
                $crop = trim(import_row_value($row, ['crop_name']));
                if ($hh === '' || $crop === '') $report['warnings'][] = 'Monitoring row missing household reference or crop name in sheet ' . $sheetName . '.';
            }
        }
    }
    $report['households_detected'] = count($seenHouseholdKeys);
    foreach ($hhCounts as $hhNo => $count) {
        if ($count >= 5 && preg_match('/^[0-9]+$/', $hhNo)) {
            $report['warnings'][] = 'HH No. ' . $hhNo . ' repeats ' . $count . ' times. Review if this should be grouped into one household.';
        }
    }
    foreach ($sheetBlocks as $sheetBlock) {
        $groups = $type === 'profiling' ? import_sheet_groups($sheetBlock, $tmpResults = ['issues' => []]) : [];
        $letters = [];
        foreach ($groups as $groupRows) {
            $rows = import_normalize_group_rows($groupRows);
            $head = $rows[0] ?? [];
            $marker = strtoupper(trim((string)($head['__group_marker'] ?? '')));
            if ($marker !== '' && preg_match('/^[A-Z]+$/', $marker)) $letters[] = $marker;
        }
        for ($i = 1; $i < count($letters); $i++) {
            $prev = import_letter_marker_index($letters[$i-1]);
            $cur = import_letter_marker_index($letters[$i]);
            if ($prev !== null && $cur !== null && $cur > ($prev + 1)) {
                $report['warnings'][] = 'Letter-only HH sequence jump detected in sheet ' . ($sheetBlock['name'] ?? 'Unknown') . ': ' . $letters[$i-1] . ' to ' . $letters[$i] . '.';
                break;
            }
        }
    }
    if ($report['barangays']) {
        $avg = array_sum($report['barangays']) / max(count($report['barangays']), 1);
        foreach ($report['barangays'] as $barangay => $count) {
            if ($count > ($avg * 2.5) || $count < max(1, $avg * 0.25)) {
                $report['warnings'][] = 'Barangay ' . $barangay . ' has an unusual row count (' . $count . ').';
            }
        }
    }
    $report['warnings'] = array_values(array_unique($report['warnings']));
    arsort($report['barangays']);
    return $report;
}

function import_recent_batches(mysqli $conn, int $limit = 10): array {
    if (!table_exists($conn, 'import_batches')) return [];
    $sql = "SELECT ib.*, COALESCE(u.full_name, u.username, CONCAT('User #', ib.imported_by)) AS importer_name FROM import_batches ib LEFT JOIN users u ON u.user_id = COALESCE(ib.imported_by, ib.created_by) ORDER BY COALESCE(ib.finished_at, ib.created_at, ib.started_at) DESC LIMIT " . (int)$limit;
    return fetch_all_assoc($conn, $sql);
}

function import_log_batch(mysqli $conn, int $userId, string $type, string $filename, array $results): void {
    if (!table_exists($conn, 'import_batches')) return;

    $issueCount = count($results['issues'] ?? []);
    $summary = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (column_exists($conn, 'import_batches', 'batch_type')) {
        $stmt = $conn->prepare("INSERT INTO import_batches (batch_type, original_file_name, imported_by, import_status, started_at, finished_at, summary_json, created_households, updated_households, created_members, updated_members, created_interviews, updated_interviews, created_crops, created_monitoring, issue_count) VALUES (?, ?, ?, 'success', NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param(
                'ssisiiiiiiiii',
                $type, $filename, $userId, $summary,
                $results['created_households'], $results['updated_households'],
                $results['created_members'], $results['updated_members'],
                $results['created_interviews'], $results['updated_interviews'],
                $results['created_crops'], $results['created_monitoring'], $issueCount
            );
            $stmt->execute();
            $stmt->close();
            return;
        }
    }

    $stmt = $conn->prepare("INSERT INTO import_batches (import_type, source_file_name, created_households, updated_households, created_members, updated_members, created_interviews, updated_interviews, created_crops, created_monitoring, issue_count, summary_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param(
        'ssiiiiiiiiisi',
        $type, $filename,
        $results['created_households'], $results['updated_households'],
        $results['created_members'], $results['updated_members'],
        $results['created_interviews'], $results['updated_interviews'],
        $results['created_crops'], $results['created_monitoring'],
        $issueCount, $summary, $userId
    );
    $stmt->execute();
    $stmt->close();
}

function import_normalize_group_rows(array $groupRows): array {
    $normalized = [];
    foreach ($groupRows as $value) {
        if (is_array($value)) {
            $hasScalar = false;
            foreach ($value as $inner) {
                if (!is_array($inner)) { $hasScalar = true; break; }
            }
            if ($hasScalar) {
                $normalized[] = $value;
            } else {
                foreach ($value as $inner) {
                    if (is_array($inner)) $normalized[] = $inner;
                }
            }
        }
    }
    return array_values($normalized);
}

function import_resolve_group_head(array $groupRows): array {
    $rows = import_normalize_group_rows($groupRows);
    if (!$rows) return [];
    foreach ($rows as $row) {
        if (is_array($row) && import_is_head_row($row)) return $row;
    }
    return is_array($rows[0]) ? $rows[0] : [];
}

function import_barangay_code(string $barangay): string {
    $barangay = import_clean_barangay_name($barangay);
    $parts = preg_split('/\s+/', strtoupper($barangay));
    $code = '';
    foreach ($parts as $part) {
        $part = preg_replace('/[^A-Z0-9]/', '', $part);
        if ($part === '') continue;
        $code .= substr($part, 0, min(strlen($part), 3));
    }
    $code = preg_replace('/[^A-Z0-9]/', '', $code);
    return substr($code !== '' ? $code : 'BRGY', 0, 6);
}

function import_next_household_code(mysqli $conn): string {
    static $counter = null;
    if ($counter === null) {
        $counter = (int)scalar($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING(household_code,4) AS UNSIGNED)),0) FROM households WHERE household_code REGEXP '^HH-[0-9]+$'", 0);
    }
    $counter++;
    return sprintf('HH-%06d', $counter);
}

function import_next_registered_hh_no(mysqli $conn, string $barangay): string {
    static $counters = [];
    $code = import_barangay_code($barangay);
    $year = date('Y');
    $key = $code . '-' . $year;
    if (!isset($counters[$key])) {
        $prefix = $conn->real_escape_string($key . '-');
        $sql = "SELECT registered_hh_no FROM households WHERE registered_hh_no LIKE '{$prefix}%' ORDER BY household_id DESC LIMIT 1";
        $last = (string)scalar($conn, $sql, '');
        $seq = 0;
        if (preg_match('/-(\d{4,})$/', $last, $m)) $seq = (int)$m[1];
        $counters[$key] = $seq;
    }
    $counters[$key]++;
    return sprintf('%s-%04d', $key, $counters[$key]);
}

function import_source_reference(array $groupRows, string $sheetName, int $sequence): string {
    $rows = import_normalize_group_rows($groupRows);
    $first = $rows[0] ?? [];
    $barangay = import_clean_barangay_name(import_row_value($first, ['barangay']));
    $sheetToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string)$sheetName));
    $barangayToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $barangay));
    $hhNo = trim(import_row_hh_no($first));
    $groupMarker = trim((string)($first['__group_marker'] ?? ''));
    if ($hhNo !== '') {
        $source = $hhNo;
    } elseif ($groupMarker !== '') {
        $source = $groupMarker;
    } else {
        $source = 'BLK-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }
    $source = strtoupper(preg_replace('/[^A-Z0-9-]+/i', '', $source));
    return trim('SRC-' . ($barangayToken !== '' ? $barangayToken : $sheetToken) . '-' . $source . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT), '-');
}

function import_source_family_key(array $groupRows, string $sheetName, int $sequence): string {
    $rows = import_normalize_group_rows($groupRows);
    $headRow = import_resolve_group_head($rows);
    $barangay = import_clean_barangay_name(import_row_value($headRow, ['barangay'], import_row_value($headRow, ['sheet_barangay'], $sheetName)));
    $barangayToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $barangay));
    $sheetToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string)$sheetName));
    $sourceHh = trim((string)(import_effective_hh_no($headRow) ?? ''));
    $headName = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', import_compose_full_name($headRow)));
    $seq = str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    $prefix = $barangayToken !== '' ? $barangayToken : ($sheetToken !== '' ? $sheetToken : 'UNKNOWN');
    if ($sourceHh !== '') {
        return 'BRGY|' . $prefix . '|FAM|' . strtoupper($sourceHh) . '|SEQ|' . $seq;
    }
    return 'BLANK|' . $prefix . '|SEQ|' . $seq . '|HEAD|' . ($headName !== '' ? $headName : 'UNKNOWN');
}

function import_derive_hh_parts(?string $hhNo): array {
    $hhNo = trim((string)$hhNo);
    if ($hhNo === '') return [null, null];
    if (preg_match('/^\s*([0-9]+)\s*[- ]?\s*([A-Za-z]+)\s*$/', $hhNo, $m)) {
        return [trim($m[1]), strtoupper(trim($m[2]))];
    }
    if (preg_match('/^\s*([0-9]+)\s*$/', $hhNo, $m)) {
        return [trim($m[1]), null];
    }
    if (preg_match('/^\s*([A-Za-z]+)\s*$/', $hhNo, $m)) {
        return [null, strtoupper(trim($m[1]))];
    }
    return [$hhNo, null];
}

function import_hh_has_letter_suffix(?string $hhNo): bool {
    [, $suffix] = import_derive_hh_parts($hhNo);
    return $suffix !== null && $suffix !== '' && (bool)preg_match('/^[A-Z]+$/', (string)$suffix);
}

function import_effective_hh_no(array $row): ?string {
    $value = trim(import_row_hh_no($row));
    return $value !== '' ? $value : null;
}

function import_household_group_key(string $barangay, ?string $hhBaseNo, string $sourceFamilyKey, ?int $barangayId = null, ?string $hhSuffix = null, ?string $clusterKey = null, ?string $blockLabel = null): string {
    $base = trim((string)$hhBaseNo);
    $suffix = strtoupper(trim((string)$hhSuffix));
    $cluster = trim((string)$clusterKey);
    $label = strtoupper(trim((string)$blockLabel));
    if ($base !== '' && $suffix !== '' && preg_match('/^[A-Z]+$/', $suffix)) {
        if ($barangayId !== null && $barangayId > 0) return 'BRGY|' . $barangayId . '|BASE|' . strtoupper($base);
        $barangayToken = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', import_clean_barangay_name($barangay)));
        return 'BRGY|' . ($barangayToken !== '' ? $barangayToken : 'UNKNOWN') . '|BASE|' . strtoupper($base);
    }
    if ($base === '' && $cluster !== '' && $label !== '' && preg_match('/^[A-Z]+$/', $label)) {
        return 'CLUSTER|' . strtoupper($cluster);
    }
    return $sourceFamilyKey !== '' ? $sourceFamilyKey : 'BLANK|' . strtoupper(preg_replace('/[^A-Z0-9]+/i', '', import_clean_barangay_name($barangay)));
}

function import_group_household_row(array $groupRows, string $sheetName, int $sequence): array {
    $rows = import_normalize_group_rows($groupRows);
    $headRow = import_resolve_group_head($rows);
    $headName = import_compose_full_name($headRow);
    if ($headName === '' && !empty($rows[0]) && is_array($rows[0])) $headName = import_compose_full_name($rows[0]);
    $sourceHh = trim((string)(import_effective_hh_no($headRow) ?? ''));
    $groupMarker = trim((string)($headRow['__group_marker'] ?? ''));
    $headRow['household_head_name'] = $headName;
    $headRow['member_name'] = $headName;
    $headRow['relationship_to_head'] = 'Head';
    $headRow['is_household_head'] = 'yes';
    $headRow['reference_no'] = import_source_reference($rows, $sheetName, $sequence);
    [$hhBaseNo, $hhSuffix] = import_derive_hh_parts($sourceHh);
    $headRow['registered_hh_no'] = $sourceHh !== '' ? $sourceHh : null;
    $headRow['official_hh_no'] = $sourceHh !== '' ? $sourceHh : null;
    $headRow['source_hh_no'] = $sourceHh !== '' ? $sourceHh : null;
    $headRow['source_hh_no_raw'] = $sourceHh;
    $headRow['hh_base_no'] = $hhBaseNo;
    $headRow['hh_suffix'] = $hhSuffix;
    $headRow['hh_is_excel_supplied'] = $sourceHh !== '' ? 1 : 0;
    $headRow['household_cluster_key'] = $headRow['__cluster_key'] ?? null;
    $headRow['source_block_label'] = $headRow['__block_label'] ?? (import_is_letter_hh_marker($groupMarker) ? strtoupper($groupMarker) : null);
    $headRow['source_sheet_name'] = $sheetName;
    $headRow['sheet_name'] = $sheetName;
    $headRow['source_family_key'] = import_source_family_key($rows, $sheetName, $sequence);
    $headRow['household_group_key'] = import_household_group_key(
        (string)($headRow['sheet_barangay'] ?? import_row_value($headRow, ['barangay'], $sheetName)),
        $hhBaseNo,
        (string)$headRow['source_family_key'],
        null,
        $hhSuffix,
        (string)($headRow['household_cluster_key'] ?? ''),
        (string)($headRow['source_block_label'] ?? '')
    );
    if (!isset($headRow['sheet_barangay']) || trim((string)$headRow['sheet_barangay']) === '') {
        $headRow['sheet_barangay'] = import_clean_barangay_name(import_row_value($headRow, ['barangay'], $sheetName));
    }
    return $headRow;
}

function import_upsert_household(mysqli $conn, array $row, int $uid, array &$results): int {
    $found = import_household_lookup($conn, $row);
    $householdId = (int)($found['household_id'] ?? 0);

    $head = import_row_value($row, ['household_head_name', 'member_name']);
    if ($head === '') return 0;

    $resolvedBarangay = import_row_barangay_name($row, import_row_value($row, ['sheet_barangay']), import_row_value($row, ['sheet_name']));
    $barangayId = import_find_barangay_id($conn, $resolvedBarangay);
    if ($barangayId <= 0) {
        $results['issues'][] = 'Skipped household because barangay was not matched: ' . ($resolvedBarangay !== '' ? $resolvedBarangay : import_row_value($row, ['barangay']));
        return 0;
    }

    $officialHh = import_effective_hh_no($row);
    $registered = $officialHh !== null && $officialHh !== '' ? $officialHh : (import_row_value($row, ['registered_hh_no']) ?: null);
    $hhBaseNo = import_row_value($row, ['hh_base_no']);
    $hhSuffix = import_row_value($row, ['hh_suffix']);
    if (($hhBaseNo === '' && $hhSuffix === '') && $officialHh !== null && $officialHh !== '') {
        [$derivedBase, $derivedSuffix] = import_derive_hh_parts($officialHh);
        $hhBaseNo = $derivedBase ?? '';
        $hhSuffix = $derivedSuffix ?? '';
    }

    $sourceFamilyKey = import_row_value($row, ['source_family_key']);
    if ($sourceFamilyKey === '') {
        $referenceKey = import_row_value($row, ['reference_no']);
        if ($referenceKey !== '') {
            $sourceFamilyKey = 'REF|' . strtoupper(preg_replace('/[^A-Z0-9|:-]+/i', '', $referenceKey));
        } elseif ($officialHh !== null && $officialHh !== '') {
            $sourceFamilyKey = 'BRGY|' . $barangayId . '|FAM|' . strtoupper($officialHh) . '|HH|' . ($householdId > 0 ? $householdId : substr(sha1(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 12));
        } else {
            $sourceFamilyKey = 'BLANK|' . $barangayId . '|HH|' . substr(sha1(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 20);
        }
    }
    $computedGroupKey = import_household_group_key(
        $resolvedBarangay,
        $hhBaseNo !== '' ? $hhBaseNo : null,
        $sourceFamilyKey,
        $barangayId,
        $hhSuffix !== '' ? $hhSuffix : null,
        import_row_value($row, ['household_cluster_key']),
        import_row_value($row, ['source_block_label'])
    );

    $sex = import_normalize_enum(import_row_value($row, ['sex', 'gender']), sex_options(), '');
    $sex = $sex !== '' ? $sex : null;
    $birthdate = import_parse_date(import_row_value($row, ['date_of_birth', 'birthdate']));
    $age = calculate_age_from_birthdate($birthdate);
    if ($age === null) $age = import_parse_int(import_row_value($row, ['age_bracket', 'age'])) ?? null;
    $contact = import_row_value($row, ['contact_number', 'contact_no', 'phone_number', 'mobile_number']);
    $contact = $contact !== '' ? $contact : null;
    $purok = import_row_value($row, ['purok_sitio']);
    $purok = $purok !== '' ? $purok : null;
    $address = $purok;
    $remarks = import_row_value($row, ['remarks']) ?: null;
    $reference = import_row_value($row, ['reference_no']) ?: null;
    $householdCode = import_row_value($row, ['household_code']) ?: null;

    if ($householdId > 0) {
        $updates = [];
        $types = '';
        $values = [];
        foreach ([
            'barangay_id' => [$barangayId, 'i'],
            'household_head_name' => [$head, 's'],
            'sex' => [$sex, 's'],
            'birthdate' => [$birthdate, 's'],
            'age' => [$age, 'i'],
            'contact_number' => [$contact, 's'],
            'purok_sitio' => [$purok, 's'],
            'full_address' => [$address, 's'],
            'remarks' => [$remarks, 's'],
            'reference_no' => [$reference, 's'],
            'source_family_key' => [$sourceFamilyKey ?: null, 's'],
            'household_group_key' => [$computedGroupKey ?: null, 's'],
            'registered_hh_no' => [$registered, 's'],
            'official_hh_no' => [$officialHh ?: null, 's'],
            'source_hh_no' => [$officialHh ?: null, 's'],
            'hh_base_no' => [$hhBaseNo ?: null, 's'],
            'hh_suffix' => [$hhSuffix ?: null, 's'],
            'hh_is_excel_supplied' => [$officialHh !== null && $officialHh !== '' ? 1 : 0, 'i'],
            'household_cluster_key' => [import_row_value($row, ['household_cluster_key']) ?: null, 's'],
            'source_block_label' => [import_row_value($row, ['source_block_label']) ?: null, 's'],
            'source_sheet_name' => [import_row_value($row, ['source_sheet_name', 'sheet_name']) ?: null, 's'],
            'updated_by' => [$uid, 'i'],
        ] as $column => [$value, $type]) {
            if (!column_exists($conn, 'households', $column)) continue;
            $updates[] = "{$column}=?";
            $types .= $type;
            $values[] = $value;
        }
        if ($updates) {
            $types .= 'i';
            $values[] = $householdId;
            $stmt = $conn->prepare('UPDATE households SET ' . implode(', ', $updates) . ' WHERE household_id=?');
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
                $results['updated_households']++;
            }
        }
        return $householdId;
    }

    if ($householdCode === null || $householdCode === '') $householdCode = import_next_household_code($conn);

    $columns = ['barangay_id', 'household_head_name', 'created_by'];
    $values = [$barangayId, $head, $uid];
    $types = 'isi';
    foreach ([
        'sex' => [$sex, 's'],
        'birthdate' => [$birthdate, 's'],
        'age' => [$age, 'i'],
        'contact_number' => [$contact, 's'],
        'purok_sitio' => [$purok, 's'],
        'full_address' => [$address, 's'],
        'remarks' => [$remarks, 's'],
        'reference_no' => [$reference, 's'],
        'source_family_key' => [$sourceFamilyKey ?: null, 's'],
        'household_group_key' => [$computedGroupKey ?: null, 's'],
        'household_code' => [$householdCode, 's'],
        'registered_hh_no' => [$registered, 's'],
        'official_hh_no' => [$officialHh ?: null, 's'],
        'source_hh_no' => [$officialHh ?: null, 's'],
        'hh_base_no' => [$hhBaseNo ?: null, 's'],
        'hh_suffix' => [$hhSuffix ?: null, 's'],
        'hh_is_excel_supplied' => [$officialHh !== null && $officialHh !== '' ? 1 : 0, 'i'],
        'household_cluster_key' => [import_row_value($row, ['household_cluster_key']) ?: null, 's'],
        'source_block_label' => [import_row_value($row, ['source_block_label']) ?: null, 's'],
        'source_sheet_name' => [import_row_value($row, ['source_sheet_name', 'sheet_name']) ?: null, 's'],
    ] as $column => [$value, $type]) {
        if (!column_exists($conn, 'households', $column)) continue;
        $columns[] = $column;
        $values[] = $value;
        $types .= $type;
    }
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare('INSERT INTO households (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')');
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $householdId = (int)$stmt->insert_id;
    $stmt->close();
    ensure_household_assets($conn, $householdId, $uid);
    $results['created_households']++;
    return $householdId;
}

function import_member_payload(array $row, array $groupHeadRow): array {
    $memberName = import_compose_full_name($row);
    $headName = import_compose_full_name($groupHeadRow);
    $relationship = import_relationship_label($row);
    $headFlag = import_is_head_row($row) || strcasecmp($memberName, $headName) === 0;

    $educationRaw = import_row_value($row, ['educational_attainment', 'education_level', 'education']);
    $education = import_normalize_enum($educationRaw, education_level_options(), $educationRaw);
    $occupationRaw = import_row_value($row, ['occupation']);
    $occupation = import_normalize_enum($occupationRaw, occupation_options(), $occupationRaw);

    $source = [
        'source_format' => 'manual_rbi_xlsx',
        'sheet_name' => import_row_value($row, ['sheet_name']),
        'barangay' => import_row_barangay_name($row, import_row_value($row, ['sheet_barangay']), import_row_value($row, ['sheet_name'])),
        'hh_no' => import_row_hh_no($row),
        'hh_no_' => trim((string)($row['hh_no_'] ?? '')) ?: import_row_hh_no($row),
    ] + $row;

    return [
        'full_name' => $memberName,
        'relationship_to_head' => $headFlag ? 'Head' : ($relationship !== '' ? $relationship : 'Member'),
        'sex' => import_normalize_enum(import_row_value($row, ['sex', 'gender']), sex_options(), ''),
        'birthdate' => import_parse_date(import_row_value($row, ['date_of_birth', 'birthdate'])),
        'age' => import_parse_int(import_row_value($row, ['age_bracket', 'age'])),
        'contact_number' => import_row_value($row, ['contact_number', 'contact_no', 'phone_number', 'mobile_number']) ?: null,
        'civil_status' => import_normalize_enum(import_row_value($row, ['civil_status']), civil_status_options(), import_row_value($row, ['civil_status']) ?: ''),
        'occupation' => $occupation ?: null,
        'education_level' => $education ?: null,
        'member_status' => 'Living in household',
        'remarks' => import_row_value($row, ['remarks']) ?: null,
        'is_primary_farmer' => import_yesno(import_row_value($row, ['is_primary_farmer'], $headFlag ? 'yes' : 'no')),
        'place_of_birth' => import_row_value($row, ['place_of_birth']) ?: null,
        'weight_kg' => import_parse_decimal(import_row_value($row, ['weight', 'weight_kg']), 2),
        'height_cm' => import_parse_decimal(import_row_value($row, ['height', 'height_cm']), 2),
        'citizenship' => import_row_value($row, ['citizenship']) ?: null,
        'language_spoken' => import_row_value($row, ['language_spoken']) ?: null,
        'religious_affiliation' => import_row_value($row, ['religious_affiliation']) ?: null,
        'employment_status' => import_row_value($row, ['employment_status']) ?: null,
        'ofw_details' => import_row_value($row, ['ofw_details']) ?: null,
        'current_skill' => import_row_value($row, ['current_skill']) ?: null,
        'desired_skill' => import_row_value($row, ['desired_skill']) ?: null,
        'unemployed_current_skill' => import_row_value($row, ['unemployed_current_skill']) ?: null,
        'unemployed_desired_skill' => import_row_value($row, ['unemployed_desired_skill']) ?: null,
        'average_monthly_income' => import_parse_decimal(import_row_value($row, ['average_monthly_income']), 2),
        'emerging_diseases' => import_row_value($row, ['emerging_diseases']) ?: null,
        'disability' => import_row_value($row, ['disability']) ?: null,
        'source_profile_json' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'notes' => json_encode([
            'employment_status' => import_row_value($row, ['employment_status']) ?: null,
            'ofw_details' => import_row_value($row, ['ofw_details']) ?: null,
            'current_skill' => import_row_value($row, ['current_skill']) ?: null,
            'desired_skill' => import_row_value($row, ['desired_skill']) ?: null,
            'unemployed_current_skill' => import_row_value($row, ['unemployed_current_skill']) ?: null,
            'unemployed_desired_skill' => import_row_value($row, ['unemployed_desired_skill']) ?: null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function import_upsert_member(mysqli $conn, int $householdId, array $row, array $groupHeadRow, int $uid, array &$results): void {
    $payload = import_member_payload($row, $groupHeadRow);
    $memberName = trim((string)$payload['full_name']);
    if ($memberName === '') return;
    $headFlag = ($payload['relationship_to_head'] ?? '') === 'Head';

    if ($headFlag) {
        $existingHeadId = (int)scalar($conn, "SELECT member_id FROM family_members WHERE household_id={$householdId} AND is_household_head=1 ORDER BY member_id DESC LIMIT 1", 0);
        $memberId = upsert_household_head_member($conn, $householdId, $payload, null, $existingHeadId ?: null);
        if ($memberId > 0) {
            if (column_exists($conn, 'households', 'head_member_id')) {
                $stmt = $conn->prepare("UPDATE households SET head_member_id=?, household_head_name=?, profile_photo_path=(SELECT member_photo_path FROM family_members WHERE member_id=? LIMIT 1) WHERE household_id=?");
                if ($stmt) { $stmt->bind_param('isii', $memberId, $memberName, $memberId, $householdId); $stmt->execute(); $stmt->close(); }
            }
            $results[$existingHeadId > 0 ? 'updated_members' : 'created_members']++;
        }
        return;
    }

    $existing = import_member_lookup($conn, $householdId, $memberName, $payload['birthdate'] ?? null, $payload['relationship_to_head'] ?? 'Member');
    if ($existing) {
        update_household_member($conn, $householdId, (int)$existing['member_id'], $payload, null, $uid);
        $results['updated_members']++;
        return;
    }
    save_household_member($conn, $householdId, $payload, null, $uid);
    $results['created_members']++;
}


function import_refresh_household_group_keys(mysqli $conn): void {
    if (!table_exists($conn, 'households')) return;
    if (function_exists('repair_household_family_source_fields')) repair_household_family_source_fields($conn);
    $official = "COALESCE(NULLIF(h.official_hh_no,''), NULLIF(h.registered_hh_no,''), NULLIF(h.source_hh_no,''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(h.source_profile_json, '$.hh_no')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(h.source_profile_json, '$.hh_no_')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(h.source_profile_json, '$.hh_no__')), ''))";
    @$conn->query("UPDATE households h SET h.official_hh_no = " . $official . ", h.source_hh_no = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL ELSE " . $official . " END, h.registered_hh_no = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL ELSE " . $official . " END, h.hh_is_excel_supplied = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN 0 ELSE 1 END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
    @$conn->query("UPDATE households h SET h.hh_base_no = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL WHEN TRIM(" . $official . ") REGEXP '^[0-9]+[[:space:]-]*[A-Za-z]+$' THEN TRIM(REGEXP_SUBSTR(TRIM(" . $official . "), '^[0-9]+')) WHEN TRIM(" . $official . ") REGEXP '^[0-9]+$' THEN TRIM(" . $official . ") ELSE TRIM(" . $official . ") END, h.hh_suffix = CASE WHEN " . $official . " IS NULL OR TRIM(" . $official . ")='' THEN NULL WHEN TRIM(" . $official . ") REGEXP '^[0-9]+[[:space:]-]*[A-Za-z]+$' THEN UPPER(REGEXP_REPLACE(TRIM(" . $official . "), '^[0-9]+[[:space:]-]*', '')) ELSE NULL END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
    @$conn->query("UPDATE households h SET h.source_family_key = CASE WHEN COALESCE(NULLIF(h.source_family_key,''), '') <> '' THEN h.source_family_key WHEN COALESCE(NULLIF(h.reference_no,''), '') <> '' THEN CONCAT('REF|', UPPER(TRIM(h.reference_no))) WHEN " . $official . " IS NOT NULL AND TRIM(" . $official . ")<>'' THEN CONCAT('BRGY|', COALESCE(h.barangay_id,0), '|FAM|', UPPER(TRIM(" . $official . ")), '|HH|', h.household_id) ELSE CONCAT('BLANK|', COALESCE(h.barangay_id,0), '|HH|', h.household_id) END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
    @$conn->query("UPDATE households h SET h.household_group_key = CASE WHEN COALESCE(NULLIF(h.hh_base_no,''), '')<>'' AND COALESCE(NULLIF(h.hh_suffix,''), '')<>'' AND UPPER(TRIM(h.hh_suffix)) REGEXP '^[A-Z]+$' THEN CONCAT('BRGY|', COALESCE(h.barangay_id,0), '|BASE|', UPPER(TRIM(h.hh_base_no))) WHEN COALESCE(NULLIF(h.hh_base_no,''), '')='' AND COALESCE(NULLIF(h.household_cluster_key,''), '')<>'' AND COALESCE(NULLIF(h.source_block_label,''), '')<>'' AND UPPER(TRIM(h.source_block_label)) REGEXP '^[A-Z]+$' THEN CONCAT('CLUSTER|', UPPER(TRIM(h.household_cluster_key))) ELSE COALESCE(NULLIF(h.source_family_key,''), CONCAT('BLANK|', COALESCE(h.barangay_id,0), '|HH|', h.household_id)) END WHERE COALESCE(h.record_status,'active') <> 'deleted'");
}

function import_upsert_interview(mysqli $conn, int $householdId, array $firstRow, int $uid, array &$results): void {
    $interviewDate = date('Y-m-d');
    $registerNo = import_row_value($firstRow, ['registered_hh_no', 'reference_no', 'household_code']) ?: null;
    $remarks = 'For Validation · Imported from family profiling workbook | Sheet: ' . import_row_value($firstRow, ['sheet_name']);
    $existing = null;
    if ($registerNo !== null) {
        $safeReg = $conn->real_escape_string($registerNo);
        $existing = fetch_one($conn, "SELECT interview_id FROM interviews WHERE household_id={$householdId} AND COALESCE(register_no,'')='{$safeReg}' ORDER BY interview_id DESC LIMIT 1");
    }
    if ($existing) {
        $stmt = $conn->prepare("UPDATE interviews SET interviewed_by=?, interview_date=?, remarks=?, status='Completed' WHERE interview_id=? AND household_id=?");
        if ($stmt) {
            $interviewId = (int)$existing['interview_id'];
            $stmt->bind_param('issii', $uid, $interviewDate, $remarks, $interviewId, $householdId);
            $stmt->execute();
            $stmt->close();
            $results['updated_interviews']++;
        }
        return;
    }
    $stmt = $conn->prepare("INSERT INTO interviews (household_id, interviewed_by, interview_date, register_no, remarks, compliance_status, status) VALUES (?, ?, ?, ?, ?, 'For Validation', 'Completed')");
    if ($stmt) {
        $stmt->bind_param('iisss', $householdId, $uid, $interviewDate, $registerNo, $remarks);
        $stmt->execute();
        $stmt->close();
        $results['created_interviews']++;
    }
}

if (isset($_GET['template'])) {
    $type = $_GET['template'] === 'monitoring' ? 'monitoring' : 'profiling';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="harvest_' . $type . '_template.csv"');
    echo import_template_csv($type);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = post('import_type');
    $sourceKind = '';
    $sheetBlocks = import_read_uploaded_rows($_FILES['import_file'] ?? [], $sourceKind);
    if (!$sheetBlocks) {
        set_flash('error', 'Upload a valid .xlsx or .csv file first.');
        header('Location: /harvest/modules/agri/import/index.php');
        exit;
    }
    $uid = (int)$user['id'];
    if (post('action') === 'preview_import') {
        $previewReport = import_preview_summary($conn, $sheetBlocks, $type === 'monitoring' ? 'monitoring' : 'profiling');
    } elseif ($type === 'profiling') {
        $sequence = 0;
        foreach ($sheetBlocks as $sheetBlock) {
            $groups = import_sheet_groups($sheetBlock, $results);
            foreach ($groups as $groupRows) {
                $groupRows = import_normalize_group_rows($groupRows);
                if (!$groupRows) continue;
                $sequence++;
                $first = import_group_household_row($groupRows, (string)($sheetBlock['name'] ?? ''), $sequence);
                $conn->begin_transaction();
                try {
                    $hid = import_upsert_household($conn, $first, $uid, $results);
                    if ($hid <= 0) {
                        $results['issues'][] = 'Skipped one family group in sheet ' . ($sheetBlock['name'] ?? 'Unknown') . ' because the household could not be matched or the head of family name was blank.';
                        $conn->rollback();
                        continue;
                    }
                    foreach ($groupRows as $row) import_upsert_member($conn, $hid, $row, $first, $uid, $results);
                    sync_household_auto_fields($conn, $hid);
                    import_upsert_cbms_lite($conn, $hid, $first, $uid);
                    import_upsert_interview($conn, $hid, $first, $uid, $results);
                    refresh_household_qualification_php($conn, $hid);
                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $results['issues'][] = 'Profiling import issue on sheet ' . ($sheetBlock['name'] ?? 'Unknown') . ': ' . $e->getMessage();
                }
            }
        }
        import_refresh_household_group_keys($conn);
        import_log_batch($conn, $uid, 'profiling', $_FILES['import_file']['name'] ?? 'profiling.xlsx', $results);
        set_flash('success', 'Family profiling file imported using RBI workbook grouping logic: same HH base in one barangay = one household, while each family block stays separate.');
    } elseif ($type === 'monitoring') {
        $rows = $sheetBlocks[0]['rows'] ?? [];
        $official = [];
        foreach (official_crop_rows($conn) as $r) $official[strtolower($r['crop_name'])] = $r['crop_name'];
        foreach ($rows as $i => $row) {
            $householdRef = import_row_value($row, ['household_code', 'reference_no', 'household_name', 'name']);
            $cropNameRaw = import_row_value($row, ['crop_name']);
            if ($householdRef === '' || $cropNameRaw === '') { $results['issues'][] = 'Row '.($i+2).': missing household reference or crop_name.'; continue; }
            $hidRow = import_household_lookup($conn, $row);
            if (!$hidRow) { $results['issues'][] = 'Row '.($i+2).': household not found - '.$householdRef; continue; }
            $hid = (int)$hidRow['household_id'];
            $cropName = $official[strtolower($cropNameRaw)] ?? '';
            if ($cropName === '') { $results['issues'][] = 'Row '.($i+2).': crop not in mayor official crop list - '.$cropNameRaw; continue; }
            $safeCrop = $conn->real_escape_string($cropName);
            $cropId = (int)scalar($conn, "SELECT crop_id FROM crops WHERE household_id={$hid} AND crop_name='{$safeCrop}' AND crop_status='Active' ORDER BY crop_id DESC LIMIT 1", 0);
            if ($cropId <= 0) {
                $stmt = $conn->prepare("INSERT INTO crops (household_id, crop_name, tree_count, plot_name, current_condition, fruiting_status, crop_status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)");
                if ($stmt) {
                    $treeCount = max(0, (int)(import_parse_int(import_row_value($row, ['tree_count_observed', 'tree_count'])) ?? 0));
                    $plot = import_row_value($row, ['address_location', 'plot_name']) ?: null;
                    $cond = import_normalize_enum(import_row_value($row, ['crop_condition']), ['Good','Bad','Needs Rehab','For Validation'], 'For Validation');
                    $fruit = import_normalize_enum(import_row_value($row, ['fruiting_status']), ['Fruiting','Not Fruiting','Unknown'], 'Unknown');
                    $stmt->bind_param('isisssi', $hid, $cropName, $treeCount, $plot, $cond, $fruit, $uid);
                    $stmt->execute();
                    $cropId = (int)$stmt->insert_id;
                    $stmt->close();
                    ensure_crop_assets($conn, $cropId, $hid, $uid);
                    $results['created_crops']++;
                }
            }
            $stmt = $conn->prepare("INSERT INTO monitoring_visits (household_id, crop_id, monitored_by, monitoring_date, tree_count_observed, fruiting_status, crop_condition, needs_rehabilitation, harvest_kg, monitoring_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Manual Search', ?)");
            if ($stmt) {
                $date = import_parse_date(import_row_value($row, ['monitoring_date'])) ?: date('Y-m-d');
                $trees = max(0, (int)(import_parse_int(import_row_value($row, ['tree_count_observed', 'tree_count'])) ?? 0));
                $fruit = import_normalize_enum(import_row_value($row, ['fruiting_status']), ['Fruiting','Not Fruiting','Unknown'], 'Unknown');
                $cond = import_normalize_enum(import_row_value($row, ['crop_condition']), ['Good','Bad','Needs Rehab','For Validation'], 'For Validation');
                $rehab = import_yesno(import_row_value($row, ['needs_rehabilitation'])) || strtolower($cond) === 'needs rehab' ? 1 : 0;
                $harvest = (float)(import_parse_decimal(import_row_value($row, ['harvest_kg']), 2) ?? 0);
                $notes = import_row_value($row, ['notes', 'address_location']) ?: null;
                $stmt->bind_param('iiisissids', $hid, $cropId, $uid, $date, $trees, $fruit, $cond, $rehab, $harvest, $notes);
                $stmt->execute();
                $stmt->close();
                $results['created_monitoring']++;
            }
            sync_household_auto_fields($conn, $hid);
            refresh_household_qualification_php($conn, $hid);
        }
        import_log_batch($conn, $uid, 'monitoring', $_FILES['import_file']['name'] ?? 'monitoring.csv', $results);
        set_flash('success', 'Monitoring file imported.');
    }

    if ($previewReport === null) {
        header('Location: /harvest/modules/agri/import/index.php');
        exit;
    }
}

$importHistory = import_recent_batches($conn, 12);
app_require('app/includes/header.php');
?>
<div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Family profiling master import</div>
    <h2 class="text-2xl font-black">Import workbook data into HARVEST</h2>
    <div class="mt-4 rounded-3xl bg-blue-50 dark:bg-blue-950/30 p-4 text-sm text-slate-600 dark:text-slate-300">Upload the original RBI workbook <strong>.xlsx</strong> so the system can read every barangay sheet, group each family correctly, detect the household head from the Excel relation column, and save the complete household profile and all family members into the HARVEST database.</div>
    <form method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4">
        <div>
            <label class="block text-sm font-semibold mb-2">Import type</label>
            <select name="import_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><option value="profiling">Family profiling workbook</option><option value="monitoring">Monitoring CSV</option></select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Workbook or CSV file</label>
            <input type="file" name="import_file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" name="action" value="import_file" class="app-btn-primary">Import file</button>
            <button type="submit" name="action" value="preview_import" class="app-btn-outline">Preview validation</button>
            <a class="app-btn-outline" href="?template=profiling">Download profiling template</a>
            <a class="app-btn-outline" href="?template=monitoring">Download monitoring template</a>
        </div>
    </form>
</section>
<section class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Workbook mapping and save rules</div>
    <h2 class="text-2xl font-black">Excel-based family profiling map</h2>
    <div class="mt-5 space-y-5 text-sm text-slate-600 dark:text-slate-300">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30 p-4">
            <div class="font-semibold text-slate-900 dark:text-white">The importer follows your RBI workbook columns directly</div>
            <div class="mt-2">HH No., Last Name, First Name, Midle Name, Ext, Purok/Street/Zone, Date of Birth, Place of Birth, Age Bracket, Civil Status, Sex, Weight, Height, Educational Attainment, Citizenship, Language Spoken, Religious Affiliation, Occupation, Employment Status, OFW details, Current Skill, Additional Skill to acquire, Skill of Un-employed Member, Average Monthly Income, Emerging Diseases, Disability, Relation to HH Heads, and Remarks.</div>
        </div>
        <div>
            <div class="font-semibold text-slate-900 dark:text-white">How family grouping works</div>
            <div class="mt-2">If a sheet has HH No., each exact HH No. is one family block. Matching base numbers in the same barangay (for example 22-A and 22-B) count as one household with multiple families. When HH No. is blank, keep HH fields blank and start a new family only when a real new head row or blank-separated family block appears.</div>
        </div>
        <div>
            <div class="font-semibold text-slate-900 dark:text-white">How the save works</div>
            <div class="mt-2">The importer creates or updates the household first, saves the head of family, saves every member in the family tree, stores the workbook-only profile fields in the family_members table, creates or updates the interview record, and refreshes household auto-fields and qualification.</div>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30 p-4">Use the original Excel workbook when importing all barangays. CSV usually contains only one active sheet.</div>
    </div>
</section>
</div>
<?php if ($previewReport): ?>
<section class="mt-6 rounded-[2rem] border border-emerald-200 bg-emerald-50/60 p-6 shadow-sm">
    <div class="text-sm text-slate-500">Import validation preview</div>
    <h2 class="text-2xl font-black">Check first before final save</h2>
    <div class="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <?= nav_cards([
            ['label'=>'Rows read','value'=>$previewReport['rows_read'],'hint'=>'Detected usable rows'],
            ['label'=>'Families detected','value'=>$previewReport['families_detected'],'hint'=>'Family groups from workbook logic'],
            ['label'=>'Households detected','value'=>$previewReport['households_detected'],'hint'=>'Grouped HH base count'],
            ['label'=>'Members detected','value'=>$previewReport['members_detected'],'hint'=>'Potential member records'],
            ['label'=>'Skipped rows','value'=>$previewReport['skipped_rows'],'hint'=>'Blank or unusable rows'],
            ['label'=>'Duplicate-like rows','value'=>$previewReport['duplicate_like_rows'],'hint'=>'Needs manual review'],
        ]) ?>
    </div>
    <div class="mt-5 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <div class="font-semibold">Warnings found</div>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <?php foreach (array_slice($previewReport['warnings'] ?? [], 0, 12) as $warning): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3"><?= e($warning) ?></div>
                <?php endforeach; ?>
                <?php if (empty($previewReport['warnings'])): ?>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">No major pattern warnings detected in this preview.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <div class="font-semibold">Barangays detected</div>
            <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Barangay</th><th class="px-4 py-3 text-right">Rows</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($previewReport['barangays'] ?? [], 0, 12, true) as $barangay => $count): ?>
                        <tr class="border-t border-slate-200"><td class="px-4 py-3"><?= e($barangay) ?></td><td class="px-4 py-3 text-right"><?= (int)$count ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
<section class="mt-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div><div class="text-sm text-slate-500">Import audit trail</div><h2 class="text-2xl font-black">Recent imports</h2></div>
        <a href="<?= e(app_url('modules/admin/logs/index.php')) ?>" class="app-btn-outline">Open logs</a>
    </div>
    <div class="mt-4 overflow-hidden rounded-3xl border border-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">File</th><th class="px-4 py-3 text-left">Imported by</th><th class="px-4 py-3 text-left">Created</th><th class="px-4 py-3 text-left">Warnings</th></tr></thead>
            <tbody>
            <?php foreach ($importHistory as $batch): ?>
                <tr class="border-t border-slate-200">
                    <td class="px-4 py-3"><?= e((string)($batch['finished_at'] ?: $batch['created_at'] ?: $batch['started_at'] ?: '-')) ?></td>
                    <td class="px-4 py-3"><?= format_status_badge((string)($batch['batch_type'] ?: $batch['import_type'] ?: 'import')) ?></td>
                    <td class="px-4 py-3"><?= e((string)($batch['original_file_name'] ?: $batch['source_file_name'] ?: '-')) ?></td>
                    <td class="px-4 py-3"><?= e((string)($batch['importer_name'] ?: 'Unknown')) ?></td>
                    <td class="px-4 py-3">HH <?= (int)($batch['created_households'] ?? 0) ?> · Members <?= (int)($batch['created_members'] ?? 0) ?></td>
                    <td class="px-4 py-3"><?= (int)($batch['issue_count'] ?? 0) ?></td>
                </tr>
            <?php endforeach; if (!$importHistory): ?>
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No import history yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_require('app/includes/footer.php'); ?>
