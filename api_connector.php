<?php
// api_connector.php
// Adaptador para la API Novanet. Provee métodos usados por index.php.
// Configuración: define constantes FFCV_COD_CLIENTE y FFCV_CLAVE_SECRETA o usa variables de entorno.

class NovanetAPI {
    private $baseUrl;
    private $cliente;
    private $clave;
    private $debug = false;

    public function __construct(array $opts = []) {
        // Determinar credenciales desde constantes, env o parámetros
        $this->cliente = $opts['cliente'] ?? (defined('FFCV_COD_CLIENTE') ? FFCV_COD_CLIENTE : getenv('FFCV_COD_CLIENTE'));
        $this->clave = $opts['clave'] ?? (defined('FFCV_CLAVE_SECRETA') ? FFCV_CLAVE_SECRETA : getenv('FFCV_CLAVE_SECRETA'));
        $this->baseUrl = $opts['baseUrl'] ?? (defined('FFCV_API_BASE') ? FFCV_API_BASE : 'https://novanet.example/api/');
        if (isset($opts['debug'])) $this->debug = (bool)$opts['debug'];
    }

    public function setDebug(bool $b) { $this->debug = $b; }

    public function authenticate(): bool {
        // Placeholder: algunos adaptadores requieren autenticar y guardar token.
        // Si su API no requiere login, simplemente devolver true.
        return true;
    }

    // Wrapper HTTP
    private function callMethod(string $endpoint, array $params = []) {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($params)) $url .= '?' . http_build_query($params);

        if ($this->debug) error_log("NovanetAPI: GET $url");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        // Si la API necesita cabeceras de autenticación, añadir aquí
        $headers = [];
        if ($this->cliente) $headers[] = 'X-Cliente: '.$this->cliente;
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            if ($this->debug) error_log('NovanetAPI curl error: '.$err);
            return null;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            if ($this->debug) error_log("NovanetAPI http $httpCode for $url");
            return null;
        }

        // Asegurar UTF-8
        $resp = $this->ensure_utf8($resp);

        $data = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug) error_log('NovanetAPI JSON decode error: '.json_last_error_msg());
            return null;
        }
        return $data;
    }

    private function ensure_utf8($str) {
        if (!is_string($str)) return $str;
        if (extension_loaded('mbstring')) {
            // Intentar detectar y convertir
            $enc = mb_detect_encoding($str, ['UTF-8','ISO-8859-1','WINDOWS-1252'], true);
            if ($enc && strtoupper($enc) !== 'UTF-8') {
                return mb_convert_encoding($str, 'UTF-8', $enc);
            }
            if (!$enc) return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
            return $str;
        }
        return utf8_encode($str);
    }

    // Métodos públicos que envuelven los endpoints concretos.
    // Ajuste los nombres de endpoints a los que use su servidor Novanet.

    public function getTemporadas(): array {
        $res = $this->callMethod('NSMApi_LstTemporadas');
        if (!is_array($res)) return [];
        // Esperamos un array simple o ['temporadas' => [...]]
        if (isset($res['temporadas']) && is_array($res['temporadas'])) return $res['temporadas'];
        return $res;
    }

    public function getCategorias(): array {
        $res = $this->callMethod('NSMApi_Categorias');
        if (!is_array($res)) return [];
        // El endpoint devuelve 'grupos_categorias' según lo indicado
        if (isset($res['grupos_categorias']) && is_array($res['grupos_categorias'])) return $res['grupos_categorias'];
        // Fallbacks comunes
        foreach (['grups_categorias','grup_categorias','grupo_categorias','grupos'] as $k) {
            if (isset($res[$k]) && is_array($res[$k])) return $res[$k];
        }
        return $res;
    }

    public function getCompeticiones(string $cod_temporada = null): array {
        $params = [];
        if ($cod_temporada) $params['cod_temporada'] = $cod_temporada;
        $res = $this->callMethod('NSMApi_Competiciones', $params);
        if (!is_array($res)) return [];
        if (isset($res['competiciones']) && is_array($res['competiciones'])) return $res['competiciones'];
        return $res;
    }

    public function getGrupos(string $cod_competicion = null): array {
        $params = [];
        if ($cod_competicion) $params['cod_competicion'] = $cod_competicion;
        $res = $this->callMethod('NSMApi_Grupos', $params);
        if (!is_array($res)) return [];
        if (isset($res['grupos']) && is_array($res['grupos'])) return $res['grupos'];
        return $res;
    }

    public function getJornadas(string $cod_grupo = null): array {
        $params = [];
        if ($cod_grupo) $params['cod_grupo'] = $cod_grupo;
        $res = $this->callMethod('NSMApi_Jornadas', $params);
        if (!is_array($res)) return [];
        if (isset($res['jornadas']) && is_array($res['jornadas'])) return $res['jornadas'];
        return $res;
    }

    public function getResultados(string $cod_grupo = null, string $cod_jornada = null): array {
        $params = [];
        if ($cod_grupo) $params['cod_grupo'] = $cod_grupo;
        if ($cod_jornada) $params['cod_jornada'] = $cod_jornada;
        $res = $this->callMethod('NSMApi_Resultados', $params);
        if (!is_array($res)) return [];
        return $res;
    }
}

// Si se requiere, el usuario puede definir constantes FFCV_COD_CLIENTE y FFCV_CLAVE_SECRETA
// Ejemplo de uso manual:
// $api = new NovanetAPI(['debug'=>true, 'baseUrl'=>'https://...']);

?>