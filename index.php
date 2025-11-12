<?php
// index.php - Integración completa: Temporadas, Grupos de Categoría, Competiciones, Grupos, Jornadas y Resultados
require_once __DIR__ . '/api_connector.php';

$api = new NovanetAPI();
if (method_exists($api, 'authenticate')) {
    try { $api->authenticate(); } catch (Exception $e) { error_log('Auth error: '.$e->getMessage()); }
}

// --- Leer GET ---
$selected_temporada = isset($_GET['cod_temporada']) && $_GET['cod_temporada'] !== '' ? (string)$_GET['cod_temporada']
    : (isset($_GET['temporada']) && $_GET['temporada'] !== '' ? (string)$_GET['temporada'] : null);

$selected_categoria = isset($_GET['categoria']) && $_GET['categoria'] !== '' ? (string)$_GET['categoria'] : null; // cod_grupo_categoria
$selected_competicion = isset($_GET['competicion']) && $_GET['competicion'] !== '' ? (string)$_GET['competicion'] : null;
$selected_grupo = isset($_GET['grupo']) && $_GET['grupo'] !== '' ? (string)$_GET['grupo'] : null;
$selected_jornada = isset($_GET['jornada']) && $_GET['jornada'] !== '' ? (string)$_GET['jornada'] : null;
$active_section = isset($_GET['section']) ? (string)$_GET['section'] : 'partidos';

// --- Helper: asegurar UTF-8 en etiquetas ---
function ensure_utf8($s) {
    if (!is_string($s)) return $s;
    if (extension_loaded('mbstring')) {
        if (mb_check_encoding($s, 'UTF-8')) return $s;
        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
    return utf8_encode($s);
}

// --- Inicializar estructuras ---
$temporadas = [];
$grupos_categorias = [];
$competiciones = [];
$grupos = [];
$jornadas_data = [];
$jornadas_html = '<span class="carousel-date active">Seleccione Grupo</span>';

// --- Cargar temporadas y grupos de categorías ---
try {
    $t = $api->getTemporadas();
    if (is_array($t)) $temporadas = $t;
} catch (Exception $e) { $temporadas = []; error_log('getTemporadas error: '.$e->getMessage()); }

try {
    $cg = $api->getCategorias(); // espera grupos_categorias
    if (is_array($cg)) $grupos_categorias = $cg;
} catch (Exception $e) { $grupos_categorias = []; error_log('getCategorias error: '.$e->getMessage()); }

// Ordenar grupos de categoría por orden_grupo_categoria asc si existe
usort($grupos_categorias, function($a, $b) {
    $av = isset($a['orden_grupo_categoria']) ? (int)$a['orden_grupo_categoria'] : 0;
    $bv = isset($b['orden_grupo_categoria']) ? (int)$b['orden_grupo_categoria'] : 0;
    return $av <=> $bv;
});

// Seleccionar temporada por defecto si no hay GET
if (!$selected_temporada && !empty($temporadas)) {
    $first = reset($temporadas);
    $selected_temporada = $first['cod_temporada'] ?? $selected_temporada;
}

// Cargar competiciones de la temporada
if ($selected_temporada) {
    try {
        $all_comp = $api->getCompeticiones($selected_temporada);
        if (is_array($all_comp)) $competiciones = $all_comp;
    } catch (Exception $e) { $competiciones = []; error_log('getCompeticiones error: '.$e->getMessage()); }
}

// Función auxiliar para obtener cod_grupo_categoria desde una competición (variantes de clave)
function get_competicion_group_code($c) {
    foreach (['cod_grupo_categoria','cod_grupo_categori','cod_grupocat','cod_grupo'] as $k) {
        if (isset($c[$k]) && $c[$k] !== '') return (string)$c[$k];
    }
    return null;
}

// Filtrar competiciones por cod_grupo_categoria y activa=1 si se seleccionó grupo de categoría
if ($selected_categoria && !empty($competiciones)) {
    $competiciones = array_values(array_filter($competiciones, function($c) use ($selected_categoria) {
        if (isset($c['activa']) && (string)$c['activa'] !== '1') return false;
        $cg = get_competicion_group_code($c);
        if ($cg === null) return false;
        if (ctype_digit((string)$cg) && ctype_digit((string)$selected_categoria)) {
            return ((int)$cg) === ((int)$selected_categoria);
        }
        return mb_strtolower(trim((string)$cg)) === mb_strtolower(trim((string)$selected_categoria));
    }));
}

// Ordenar competiciones por cod_competicion asc
usort($competiciones, function($a, $b) {
    $av = isset($a['cod_competicion']) ? (int)$a['cod_competicion'] : 0;
    $bv = isset($b['cod_competicion']) ? (int)$b['cod_competicion'] : 0;
    return $av <=> $bv;
});

// Auto-seleccionar primera competición si no hay seleccionada
if (!$selected_competicion && !empty($competiciones)) {
    $firstc = reset($competiciones);
    $selected_competicion = $firstc['cod_competicion'] ?? $selected_competicion;
}

// Cargar grupos si hay competición seleccionada
if ($selected_competicion) {
    try {
        $g = $api->getGrupos($selected_competicion);
        if (is_array($g)) $grupos = $g;
    } catch (Exception $e) { $grupos = []; error_log('getGrupos error: '.$e->getMessage()); }
}

// Auto-seleccionar primer grupo
if (!$selected_grupo && !empty($grupos)) {
    $firstg = reset($grupos);
    $selected_grupo = $firstg['cod_grupo'] ?? $selected_grupo;
}

// Cargar jornadas
if ($selected_grupo) {
    try {
        $j = $api->getJornadas($selected_grupo);
        if (is_array($j)) $jornadas_data = $j;
    } catch (Exception $e) { $jornadas_data = []; error_log('getJornadas error: '.$e->getMessage()); }

    if (!$selected_jornada && !empty($jornadas_data)) {
        $now = time(); $closest = null;
        foreach ($jornadas_data as $jr) {
            if (!empty($jr['fecha'])) {
                $ts = strtotime($jr['fecha']);
                if ($ts !== false && $ts >= $now) { $closest = $jr['cod_jornada']; break; }
            }
        }
        if (!$closest) { $last = end($jornadas_data); $closest = $last['cod_jornada'] ?? $closest; reset($jornadas_data); }
        if ($closest) $selected_jornada = $closest;
    }

    if (!empty($jornadas_data)) {
        $jornadas_html = '';
        foreach ($jornadas_data as $jr) {
            $fecha_form = '';
            if (!empty($jr['fecha'])) { try { $d = new DateTime($jr['fecha']); $fecha_form = ', '.$d->format('j M'); } catch (Exception $e) { $fecha_form = ''; } }
            $active_class = (isset($jr['cod_jornada']) && (string)$jr['cod_jornada'] === (string)$selected_jornada) ? 'active' : '';
            $url_params = http_build_query([
                'section' => $active_section,
                'cod_temporada' => $selected_temporada,
                'categoria' => $selected_categoria,
                'competicion' => $selected_competicion,
                'grupo' => $selected_grupo,
                'jornada' => $jr['cod_jornada'] ?? ''
            ]);
            $jornadas_html .= '<a href="?'.htmlspecialchars($url_params, ENT_QUOTES, 'UTF-8').'" class="carousel-date '.$active_class.'">'.htmlspecialchars($jr['nombre'] ?? 'Jornada', ENT_QUOTES, 'UTF-8').$fecha_form.'</a>';
        }
    }
}

// ---------------------- RENDER HTML ----------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Competiciones FFCV</title>
<link rel="stylesheet" href="./css/style.css">
<style>
.content-box{padding:12px;background:#fff;border-radius:6px}
label{display:block;margin-top:8px;font-weight:600}
select{width:100%;max-width:560px;padding:6px;margin-top:4px}
.carousel-date{display:inline-block;margin:0 6px;padding:6px 8px;border-radius:4px;background:#f0f0f0}
.carousel-date.active{background:#0073e6;color:#fff}
</style>
</head>
<body>

<?php if (file_exists(__DIR__.'/includes/header.php')) require __DIR__.'/includes/header.php'; ?>

<main>
<section class="filters-api-section active">
    <h2>Selección de Filtros</h2>
    <div class="content-box">
        <form method="GET" action="index.php" id="filter-form">
            <input type="hidden" name="section" value="<?php echo htmlspecialchars($active_section, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- TEMPORADA -->
            <label for="cod_temporada">Temporada</label>
            <select name="cod_temporada" id="cod_temporada">
                <option value="">-- Seleccione Temporada --</option>
                <?php foreach ($temporadas as $t):
                    $cod = $t['cod_temporada'] ?? '';
                    $nombre = ensure_utf8($t['nombre'] ?? $cod);
                    $sel = ((string)$cod === (string)$selected_temporada) ? ' selected' : '';
                ?>
                <option value="<?php echo htmlspecialchars($cod, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>

            <!-- GRUPOS DE CATEGORÍA -->
            <label for="categoria">Categoría</label>
            <select name="categoria" id="categoria">
                <option value="">-- Seleccione Categoría --</option>
                <?php if (!empty($grupos_categorias)):
                    foreach ($grupos_categorias as $grupo):
                        $value = isset($grupo['cod_grupo_categoria']) ? (string)$grupo['cod_grupo_categoria'] : (isset($grupo['cod_grupo']) ? (string)$grupo['cod_grupo'] : (string)($grupo['nombre'] ?? ''));
                        $label = ensure_utf8($grupo['nombre'] ?? $value);
                        $sel = ((string)$value === (string)$selected_categoria) ? ' selected' : '';
                        echo '<option value="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'.$sel.'>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</option>';
                    endforeach;
                else:
                    echo '<option value="">-- Sin grupos de categoría --</option>';
                endif;?>
            </select>

            <!-- COMPETICIONES -->
            <label for="competicion">Competición</label>
            <select name="competicion" id="competicion">
                <option value="">-- Seleccione Competición --</option>
                <?php foreach ($competiciones as $c):
                    $codc = $c['cod_competicion'] ?? '';
                    $nomc = ensure_utf8($c['nombre'] ?? $codc);
                    $selc = ((string)$codc === (string)$selected_competicion) ? ' selected' : '';
                    echo '<option value="'.htmlspecialchars($codc, ENT_QUOTES, 'UTF-8').'"'.$selc.'>'.htmlspecialchars($nomc, ENT_QUOTES, 'UTF-8').'</option>';
                endforeach; ?>
            </select>

            <!-- GRUPO -->
            <label for="grupo">Grupo</label>
            <select name="grupo" id="grupo">
                <option value="">-- Seleccione Grupo --</option>
                <?php foreach ($grupos as $g):
                    $codg = $g['cod_grupo'] ?? '';
                    $nomg = ensure_utf8($g['nombre'] ?? $codg);
                    $selg = ((string)$codg === (string)$selected_grupo) ? ' selected' : '';
                    echo '<option value="'.htmlspecialchars($codg, ENT_QUOTES, 'UTF-8').'"'.$selg.'>'.htmlspecialchars($nomg, ENT_QUOTES, 'UTF-8').'</option>';
                endforeach; ?>
            </select>

            <div style="margin-top:12px">
                <label>Jornadas</label>
                <div class="carousel-dates"><?php echo $jornadas_html; ?></div>
            </div>

            <div style="margin-top:12px">
                <button type="submit">Aplicar</button>
            </div>
        </form>
    </div>
</section>

<section id="partidos" style="margin-top:18px">
    <h2>Partidos y Resultados</h2>
    <div class="content-box">
        <?php
        if ($selected_grupo && $selected_jornada) {
            try {
                $resultados = $api->getResultados($selected_grupo, $selected_jornada);
                if (!is_array($resultados)) $resultados = [];
            } catch (Exception $e) { $resultados = []; error_log('getResultados error: '.$e->getMessage()); }

            if (!empty($resultados['partidos'])) {
                $current_jornada_name = '';
                foreach ($jornadas_data as $jr) {
                    if (($jr['cod_jornada'] ?? null) == $selected_jornada) { $current_jornada_name = $jr['nombre'] ?? ''; break; }
                }
                echo '<h3>Jornada '.htmlspecialchars($current_jornada_name, ENT_QUOTES, 'UTF-8').'</h3>';
                echo '<ul>';
                foreach ($resultados['partidos'] as $m) {
                    $local = $m['nombre_equipo_local'] ?? 'Local';
                    $visit = $m['nombre_equipo_visitante'] ?? 'Visitante';
                    $res_local = ($m['res_local'] ?? '') !== '' ? $m['res_local'] : '?';
                    $res_visit = ($m['res_visitante'] ?? '') !== '' ? $m['res_visitante'] : '?';
                    $fecha = 'Sin fecha';
                    if (!empty($m['fecha_partido'])) { try { $d = new DateTime($m['fecha_partido']); $fecha = $d->format('d/m/Y H:i'); } catch (Exception $e) {} }
                    echo '<li>'.htmlspecialchars($local, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($res_local.' - '.$res_visit, ENT_QUOTES, 'UTF-8').' '.htmlspecialchars($visit, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8').')</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No hay partidos registrados para la jornada seleccionada.</p>';
            }
        } else {
            echo '<p>Seleccione Temporada, Competición y Grupo para ver partidos.</p>';
        }
        ?>
    </div>
</section>
</main>

<?php if (file_exists(__DIR__.'/includes/footer.php')) require __DIR__.'/includes/footer.php'; ?>

<script>
document.getElementById('cod_temporada')?.addEventListener('change', function() {
    document.getElementById('competicion').value = '';
    document.getElementById('grupo').value = '';
    this.form.submit();
});
document.getElementById('categoria')?.addEventListener('change', function() {
    document.getElementById('competicion').value = '';
    document.getElementById('grupo').value = '';
    this.form.submit();
});
document.getElementById('competicion')?.addEventListener('change', function() {
    document.getElementById('grupo').value = '';
    this.form.submit();
});
document.getElementById('grupo')?.addEventListener('change', function() { this.form.submit(); });
</script>
</body>
</html>