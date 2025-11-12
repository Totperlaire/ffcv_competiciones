<?php 
// -------------------------------------------------------------------------
// VARIABLES PASADAS DESDE index.php (SE INICIALIZAN AQUÍ COMO FALLBACK)
// -------------------------------------------------------------------------
if (!isset($active_section)) $active_section = 'partidos';
if (!isset($jornadas_html)) $jornadas_html = '<span class="carousel-date active">Seleccione Grupo</span>';


// Mapa para obtener el título completo de la sección activa
$section_titles = [
    'partidos' => 'PARTIDOS Y RESULTADOS', 
    'clasificaciones' => 'CLASIFICACIONES',
    'sanciones' => 'SANCIONES', 
    'goleadores' => 'RANKING DE GOLEADORES/AS',
    'porteros' => 'RANKING DE PORTEROS/AS',
    'datos' => 'DATOS', 
];

// Asignamos el título basado en la sección activa
$current_title = $section_titles[$active_section] ?? 'COMPETICIONES FFCV';

/**
 * Función auxiliar para determinar si un elemento de menú está activo.
 */
function is_active($target, $current) {
    return $target === $current ? 'active' : '';
}
?>

<header>
    <div class="header-content-wrapper">
        <div class="header-top">
            
            <div class="logo-container">
                <img src="https://ffcv.es/wp/ffcv_competiciones/img/logo_ffcv.png" alt="Logo FFCV">
            </div>

            <nav>
                <ul class="menu">
                    <li class="<?php echo is_active('partidos', $active_section); ?>">
                        <a href="?section=partidos">PARTIDOS</a>
                    </li>
                    <li class="<?php echo is_active('clasificaciones', $active_section); ?>">
                        <a href="?section=clasificaciones">CLASIFICACIONES</a>
                    </li>
                    <li class="<?php echo is_active('sanciones', $active_section); ?>">
                        <a href="?section=sanciones">SANCIONES</a>
                    </li>
                    
                    <li class="dropdown <?php echo (in_array($active_section, ['goleadores', 'porteros', 'datos'])) ? 'active' : ''; ?>">
                        <a href="#">DATOS</a>
                        <ul class="submenu">
                            <li class="<?php echo is_active('goleadores', $active_section); ?>">
                                <a href="?section=goleadores">Goleadores/as</a>
                            </li>
                            <li class="<?php echo is_active('porteros', $active_section); ?>">
                                <a href="?section=porteros">Porteros/as</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="header-bottom">
            <h1 class="section-title"><?php echo htmlspecialchars($current_title); ?></h1>
            
            <div class="carousel-container">
                <a href="#" class="carousel-arrow">&lt;</a> 
                
                <div class="carousel-dates">
                    <?php echo $jornadas_html; ?>
                </div>
                
                <a href="#" class="carousel-arrow">&gt;</a>
            </div>
        </div>
    </div>
</header>