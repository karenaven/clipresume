<?php
namespace mod_clipresume\task;

defined('MOODLE_INTERNAL') || die();
ini_set('memory_limit', '3G');

class process_video_task extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('process_video_task', 'clipresume');
    }

    public function execute()
    {
        global $DB, $CFG;

        mtrace("----------------------------------------------------------------------------------------------------------");
        mtrace("Iniciando el proceso...");
        // Rutas de los archivos JSON
        $credentials_path = $CFG->dataroot . '\clipresume_credentials.json';
        $config_path = $CFG->dataroot . '\clipresume_configurations.json';

        // Función para leer JSON
        function leer_json($path)
        {
            if (!file_exists($path)) {
                echo "No se encontró el archivo JSON en: $path";
                return false;
            }
            $content = file_get_contents($path);
            $data = json_decode($content, true);
            if (!$data) {
                echo "Error al leer el contenido del archivo JSON en: $path";
                return false;
            }
            return $data;
        }

        // Leer los datos de credentials.json
        $config_data = leer_json($config_path);
        if ($config_data) {
            $folder_id = $config_data['drive_folder_id'] ?? null;
            $client_id = $config_data['zoom_client_id'] ?? null;
            $client_secret = $config_data['zoom_client_secret'] ?? null;
            $account_id = $config_data['zoom_account_id'] ?? null;
            $user_id = $config_data['zoom_user_id'] ?? null;
        }

        // Leer los datos de configuration.json
        $credentials = leer_json($credentials_path);

        // Validar y mostrar los datos cargados
        if ($credentials && $config_data) {
            mtrace("Credenciales y configuración leídas correctamente.");
        } else {
            mtrace("Error al leer las credenciales o la configuración.");
        }

        // Obtener token de acceso
        $obtener_token_acceso = function ($credentials) {
            mtrace("Generando y obteniendo token de acceso...");

            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');

            $now = time();
            $expires = $now + 3600; // 1 hour

            $unencryptedPayload = json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/drive',
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=' . $jwt);

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
                mtrace("Token de acceso de Google Drive obtenido correctamente.");
                return $response_data['access_token'];
            } else {
                mtrace("Error al obtener el token de acceso: " . json_encode($response_data));
                return false;
            }
        };
        $access_token_drive = $obtener_token_acceso($credentials);


        //INICIO Zoom
        $getAccessTokenZoom = function ($client_id, $client_secret, $account_id) {
            $token_url = "https://zoom.us/oauth/token";

            $headers = [
                'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret)
            ];

            // Datos de la solicitud
            $data = [
                'grant_type' => 'account_credentials',
                'account_id' => $account_id
            ];
            // Inicializa cURL
            $ch = curl_init();

            // Configura la solicitud cURL
            curl_setopt($ch, CURLOPT_URL, $token_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Ejecuta la solicitud
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Cierra cURL
            curl_close($ch);

            // Procesa la respuesta
            if ($http_code === 200) {
                $response_data = json_decode($response, true);
                return $response_data['access_token'];
            } else {
                mtrace("Error $http_code: $response");
                return null;
            }
        };

        $access_token_zoom = $getAccessTokenZoom($client_id, $client_secret, $account_id);
        if ($access_token_zoom) {
            mtrace("Access Token Zoom obtenido correctamente.");
        } else {
            mtrace("Error al obtener el token de acceso.");
        }

        $getLastMeetingId = function ($access_token, $user_id) {
            $from = '2024-10-15';
            $to = date('Y-m-d');
            $url = "https://api.zoom.us/v2/users/$user_id/recordings?from=" . urlencode($from) . "&to=" . urlencode($to);

            mtrace("Obteniendo ID de la última reunión...");

            $options = array(
                'http' => array(
                    'header' => "Authorization: Bearer $access_token\r\n",
                    'method' => 'GET',
                ),
            );
            $meeting_id = 0;
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            if ($result === FALSE) {
                mtrace("ERROR " . $result);
            } else {
                $meetings = json_decode($result, true);
                $latest_meeting = reset($meetings['meetings']);
                $meeting_id = $latest_meeting['id'];
            }
            return $meeting_id;
        };

        $meeting_id = $getLastMeetingId($access_token_zoom, $user_id);

        $getMeetingById = function ($access_token, $meeting_id) {
            $url = "https://api.zoom.us/v2/meetings/$meeting_id/recordings";

            $options = array(
                'http' => array(
                    'header' => "Authorization: Bearer $access_token\r\n",
                    'method' => 'GET',
                ),
            );

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            if ($result === FALSE) { /* Manejar error */
                mtrace('ERROR obteniendo meeting ' . $result);
            }

            $recordings = json_decode($result, true);

            return $recordings;
        };

        $recordings = $getMeetingById($access_token_zoom, $meeting_id);

        $getDownloadLinks = function ($recordings) {
            $links = [
                'videos' => [],
                'transcripts' => [],
                'audio' => [],
                'chat' => []
            ];

            foreach ($recordings['recording_files'] as $file) {
                if ($file['file_type'] === 'MP4') {
                    $links['videos'][] = $file;
                } elseif ($file['file_type'] === 'TRANSCRIPT') {
                    $links['transcripts'][] = $file;
                } elseif ($file['file_type'] === 'M4A') {
                    $links['audio'][] = $file;
                } elseif ($file['file_type'] === 'CHAT') {
                    $links['chat'][] = $file;
                }
            }
            return $links;
        };

        $download_links = $getDownloadLinks($recordings);

        function downloadFile($file_url, $zoom_access_token)
        {
            mtrace("Descargando archivo desde Zoom...");

            // Usa cURL para descargar el archivo desde Zoom con autenticación
            $ch = curl_init($file_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Permitir que cURL siga redirecciones
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $zoom_access_token"
            ]);
            $file_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200 || $file_data === false) {
                mtrace("Error al obtener el contenido del archivo. Código de respuesta: $http_code");
                return null;
            }

            return $file_data;
        }

        //INICIO Drive
        function createFolder($drive_access_token, $folder_id)
        {
            mtrace("Creando carpeta en Google Drive...");

            // Crear una carpeta con la fecha actual como nombre
            $folder_name = date('Y-m-d');
            $folder_metadata = [
                'name' => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$folder_id]
            ];

            $ch = curl_init('https://www.googleapis.com/drive/v3/files');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $drive_access_token",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($folder_metadata));
            $folder_response = curl_exec($ch);
            $folder_info = json_decode($folder_response, true);
            curl_close($ch);

            if (!isset($folder_info['id'])) {
                mtrace("Error al crear la carpeta en Google Drive");
                return false;
            }

            return $folder_info['id']; // Retornar el ID de la nueva carpeta
        }

        function uploadFileToDrive($file_data, $file_name, $file_type, $drive_access_token, $folder_id)
        {
            mtrace("Subiendo archivo a Google Drive...");

            // Subir el archivo a la nueva carpeta
            $boundary = uniqid();
            $delimiter = "--" . $boundary;
            $eol = "\r\n";

            // Metadatos del archivo
            $metadata = [
                'name' => $file_name,
                'parents' => $folder_id ? [$folder_id] : [],
            ];

            // Construir el cuerpo en formato multipart
            $body = $delimiter . $eol
                . 'Content-Type: application/json; charset=UTF-8' . $eol . $eol
                . json_encode($metadata) . $eol
                . $delimiter . $eol
                . "Content-Type: $file_type" . $eol . $eol
                . $file_data . $eol
                . $delimiter . "--";

            $headers = [
                "Authorization: Bearer $drive_access_token",
                "Content-Type: multipart/related; boundary=" . $boundary,
                "Content-Length: " . strlen($body),
            ];

            $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                mtrace("Error al subir el archivo a Google Drive.");
                return null;
            }

            $file_info = json_decode($response, true);
            if (isset($file_info['id'])) {
                mtrace("Archivo subido exitosamente. ID de archivo: " . $file_info['id']);
                return $file_info['id'];
            } else {
                mtrace("Error al obtener el ID del archivo.");
                return null;
            }
        }

        function uploadToDrive($file_url, $zoom_access_token, $drive_access_token, $file_name, $folder_id, $file_type, $meeting_id, $recording_id)
        {
            // Descargar el archivo desde Zoom
            $file_data = downloadFile($file_url, $zoom_access_token);
            if ($file_data === null) {
                return null; // Salir si hubo un error en la descarga
            }

            // Subir el archivo a la carpeta ya creada y obtener su ID
            $file_id = uploadFileToDrive($file_data, $file_name, $file_type, $drive_access_token, $folder_id);

            // Eliminar el archivo de Zoom después de subirlo a Google Drive
            if ($file_id) {
                deleteFileFromZoom($meeting_id, $recording_id, $zoom_access_token);
            }


            return $file_id;
        }

        // Crear la carpeta una vez antes de subir los archivos
        $folder_id = createFolder($access_token_drive, $folder_id);
        if ($folder_id === false) {
            mtrace("No se pudo crear la carpeta. Terminando.");
            exit;
        }

        // Nueva función para eliminar archivos de Zoom
        function deleteFileFromZoom($meeting_id, $recording_id, $zoom_access_token)
        {
            $url = "https://api.zoom.us/v2/meetings/$meeting_id/recordings/$recording_id";

            mtrace("Eliminando archivo de Zoom con URL: $url");

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $zoom_access_token"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 204) {
                mtrace("Archivo eliminado exitosamente de Zoom.");
                return true;
            } else {
                mtrace("Error al eliminar el archivo de Zoom. Código de respuesta: $http_code");
                return false;
            }
        }

        // Ciclo para subir videos
        $links = $download_links;

        $index_clip = 0;
        foreach ($links['videos'] as $index => $video) {
            $download_url = $video['download_url'];
            $recording_id = $video['id'];
            $file_name = 'Video_' . $index . '.mp4';
            if (isset($video['encryption_fingerprint'])) {
                $index_clip++;
                $file_name = 'Clip_' . $index_clip . ' - ' . $video['recording_start'] . '.mp4';
            } else {
                $index++;
                $file_name = 'Video Completo_' . $index . ' - ' . $video['recording_start'] . '.mp4';
            }
            uploadToDrive($download_url, $access_token_zoom, $access_token_drive, $file_name, $folder_id, "video/mp4", $meeting_id, $recording_id);
        }

        // Ciclo para subir transcripciones
        foreach ($links['transcripts'] as $index => $transcript_url) {
            $index++;
            $download_url = $transcript_url['download_url'];
            $recording_id = $transcript_url['id'];
            mtrace("Subiendo Transcripciones a Google Drive...");
            $file_name = "Transcript_" . $index . ".vtt";
            uploadToDrive($download_url, $access_token_zoom, $access_token_drive, $file_name, $folder_id, "text/vtt", $meeting_id, $recording_id);
        }

        // Eliminar audios de Zoom
        foreach ($links['audio'] as $index => $audio) {
            $audio_id = $audio['id'];
            deleteFileFromZoom($meeting_id, $audio_id, $access_token_zoom);
        }

        // Eliminar chats de Zoom
        foreach ($links['chat'] as $index => $chat) {
            $chat_id = $chat['id'];
            deleteFileFromZoom($meeting_id, $chat_id, $access_token_zoom);
        }

        mtrace("----------------------------------------------------------------------------------------------------------");
        mtrace("Fin del proceso...");
    }
}