<?php
namespace local_clipresume\task;

defined('MOODLE_INTERNAL') || die();

class process_video_task extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('process_video_task', 'local_clipresume');
    }

    public function execute() {
        global $DB, $CFG;
        $credentials_path = $CFG->dirroot.'\clipresume\path_to_google_credentials.json';
       
        // Leer credenciales
        $leer_credenciales = function($credentials_path)  {
        mtrace("Leyendo credenciales...");
        $credentials = json_decode(file_get_contents($credentials_path), true);
        if (!$credentials) {
            mtrace("Error al leer las credenciales desde $credentials_path");
            return false;
        }
        mtrace("Credenciales leídas correctamente.");
        return $credentials;
        };

        $credentials = $leer_credenciales($credentials_path);
        // Obtener token de acceso
        $obtener_token_acceso = function($credentials)  {
        mtrace("Generando y obteniendo token de acceso...");

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');

        $now = time();
        $expires = $now + 3600; // 1 hour

        $unencryptedPayload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'aud' => 'https://www.googleapis.com/oauth2/v4/token',
            'exp' => $expires,
            'iat' => $now
        ]);
        $payload = rtrim(strtr(base64_encode($unencryptedPayload), '+/', '-_'), '=');
        mtrace("Payload JWT generado.");

        $signature = '';
        $private_key = openssl_pkey_get_private($credentials['private_key']);
        if (!$private_key) {
            mtrace("Error al obtener la clave privada.");
            return false;
        }

        openssl_sign("$header.$payload", $signature, $private_key, 'SHA256');
        if (PHP_VERSION_ID < 80000) {
            openssl_free_key($private_key);
        }

        if (!$signature) {
            mtrace("Error al firmar el JWT.");
            return false;
        }
        mtrace("JWT firmado correctamente.");

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwt = "$header.$payload.$signature";

        mtrace("JWT generado exitosamente.");

        $ch = curl_init('https://www.googleapis.com/oauth2/v4/token');
        if (!$ch) {
            mtrace("Error al inicializar cURL.");
            return false;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion='.$jwt);

        $response = curl_exec($ch);
        if ($response === false) {
            mtrace("Error en la solicitud cURL: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        mtrace("Solicitud cURL completada.");

        $response_data = json_decode($response, true);
        if (isset($response_data['access_token'])) {
            mtrace("Token de acceso obtenido correctamente.");
            return $response_data['access_token'];
        } else {
            mtrace("Error al obtener el token de acceso: " . json_encode($response_data));
            return false;
        }
        };
        $access_token = $obtener_token_acceso($credentials);

        // obtener videos y eliminar de Google Drive
        $obtener_y_eliminar_videos_de_drive = function($access_token, $folder_id) {
            mtrace("Iniciando la obtención de todos los videos desde la carpeta de Google Drive...");
        
            // Buscar todos los videos en la carpeta de Google Drive
            //$query = "'$folder_id' in parents and mimeType contains 'video/'";
            $query = $folder_id;
            //$ch = curl_init("https://www.googleapis.com/drive/v3/files?q=" . urlencode($query));
            $ch = curl_init("https://www.googleapis.com/drive/v3/files?q='$folder_id'+in+parents");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ]);
        
            $response = curl_exec($ch);
            if ($response === false) {
                mtrace("Error en la solicitud cURL: " . curl_error($ch));
                curl_close($ch);
                return false;
            }
            mtrace($response);
            $response_data = json_decode($response, true);
            curl_close($ch);
            mtrace($response_data);

            if (empty($response_data['files'])) {
                mtrace("No se encontraron videos en la carpeta.");
                return false;
            }
        
            mtrace("Se encontraron " . count($response_data['files']) . " videos en la carpeta.");
        
            // Recorrer cada video encontrado
            foreach ($response_data['files'] as $file) {
                $file_id = $file['id'];
                $file_name = $file['name'];
                mtrace("Procesando video: $file_name (ID: $file_id)");
        
                // Obtener enlace de descarga del video
                $download_link = "https://www.googleapis.com/drive/v3/files/$file_id?alt=media";
                $ch = curl_init($download_link);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $access_token"
                ]);
        
                $video_content = curl_exec($ch);
                if ($video_content === false) {
                    mtrace("Error al descargar el video '$file_name': " . curl_error($ch));
                    curl_close($ch);
                    return false;
                }
        
                curl_close($ch);
                mtrace("Video '$file_name' descargado exitosamente.");
        
                // Aquí puedes procesar el contenido del video ($video_content) según sea necesario
        
                // Eliminar el video del Drive
                $ch = curl_init("https://www.googleapis.com/drive/v3/files/$file_id");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $access_token"
                ]);
        
                $response = curl_exec($ch);
                if ($response === false) {
                    mtrace("Error al eliminar el video '$file_name': " . curl_error($ch));
                    curl_close($ch);
                    return false;
                }
        
                curl_close($ch);
                mtrace("Video '$file_name' eliminado de Google Drive.");
            }
        
            mtrace("Todos los videos han sido procesados y eliminados.");
            return true;
        };

        $obtener_y_eliminar_videos_de_drive($access_token, "1cijCe6C10O_q-DwZACtZ-QBfB_9bvNVD");
    }
}