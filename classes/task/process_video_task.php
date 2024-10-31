<?php
namespace local_clipresume\task;

defined('MOODLE_INTERNAL') || die();

class process_video_task extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('process_video_task', 'local_clipresume');
    }

    public function execute()
    {
        global $DB, $CFG;

        //INICIO Google Drive
        $credentials_path = $CFG->dirroot . '\local\clipresume\path_to_google_credentials.json';

        // // Leer credenciales
        $leer_credenciales = function ($credentials_path) {
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
                mtrace("Token de acceso obtenido correctamente.");
                return $response_data['access_token'];
            } else {
                mtrace("Error al obtener el token de acceso: " . json_encode($response_data));
                return false;
            }
        };
        $access_token_drive = $obtener_token_acceso($credentials);

        // // obtener videos y eliminar de Google Drive
        // $obtener_y_eliminar_videos_de_drive = function($access_token, $folder_id) {
        //     mtrace("Iniciando la obtención de todos los videos desde la carpeta de Google Drive...");

        //     // Buscar todos los videos en la carpeta de Google Drive
        //     $query = "'$folder_id' in parents and mimeType contains 'video/'";
        //     $ch = curl_init("https://www.googleapis.com/drive/v3/files?q=" . urlencode($query));
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //     curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //         "Authorization: Bearer $access_token",
        //         "Content-Type: application/json"
        //     ]);

        //     $response = curl_exec($ch);
        //     if ($response === false) {
        //         mtrace("Error en la solicitud cURL: " . curl_error($ch));
        //         curl_close($ch);
        //         return false;
        //     }
        //     mtrace($response);
        //     $response_data = json_decode($response, true);
        //     curl_close($ch);
        //     mtrace($response_data);

        //     if (empty($response_data['files'])) {
        //         mtrace("No se encontraron videos en la carpeta.");
        //         return false;
        //     }

        //     mtrace("Se encontraron " . count($response_data['files']) . " videos en la carpeta.");

        //     // Recorrer cada video encontrado
        //     foreach ($response_data['files'] as $file) {
        //         $file_id = $file['id'];
        //         $file_name = $file['name'];
        //         mtrace("Procesando video: $file_name (ID: $file_id)");

        //         // Obtener enlace de descarga del video
        //         $download_link = "https://www.googleapis.com/drive/v3/files/$file_id?alt=media";
        //         $ch = curl_init($download_link);
        //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //         curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //             "Authorization: Bearer $access_token"
        //         ]);

        //         $video_content = curl_exec($ch);
        //         if ($video_content === false) {
        //             mtrace("Error al descargar el video '$file_name': " . curl_error($ch));
        //             curl_close($ch);
        //             return false;
        //         }

        //         curl_close($ch);
        //         mtrace("Video '$file_name' descargado exitosamente.");

        //         // Aquí puedes procesar el contenido del video ($video_content) según sea necesario


        //         // Eliminar el video del Drive
        //         $ch = curl_init("https://www.googleapis.com/drive/v3/files/$file_id");
        //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        //         curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //             "Authorization: Bearer $access_token"
        //         ]);

        //         $response = curl_exec($ch);
        //         $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Obtén el código de estado HTTP
        //         $error = curl_error($ch); // Captura cualquier error en cURL
        //         curl_close($ch);

        //         // Depuración detallada
        //         if ($http_status == 204) {
        //             mtrace("Archivo eliminado exitosamente.");
        //         } else {
        //             mtrace("Error al intentar eliminar el archivo.\n");
        //             mtrace("Código de estado HTTP: " . $http_status . "\n");
        //             mtrace("Respuesta del servidor: " . $response . "\n");
        //             if ($error) {
        //                 mtrace("Error de cURL: " . $error . "\n");
        //             }
        //         }

        //         $ch = curl_init("https://www.googleapis.com/drive/v3/files/$file_id/permissions");
        //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //         curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //             "Authorization: Bearer $access_token",
        //             "Content-Type: application/json"
        //         ]);

        //         $response = curl_exec($ch);
        //         curl_close($ch);
        //         var_dump($response);
        //         // if ($response === false) {
        //         //     mtrace("Error al eliminar el video '$file_name': " . curl_error($ch));
        //         //     curl_close($ch);
        //         //     return false;
        //         // }

        //         // curl_close($ch);
        //         // mtrace("Video '$file_name' eliminado de Google Drive.");
        //     }

        //     mtrace("Todos los videos han sido procesados y eliminados.");
        //     return true;
        // };

        // $obtener_y_eliminar_videos_de_drive($access_token, "1cijCe6C10O_q-DwZACtZ-QBfB_9bvNVD");
        //FIN Google Drive


        //INICIO Zoom
        $getAccessTokenZoom = function ($client_id, $client_secret, $account_id) {
            $token_url = "https://zoom.us/oauth/token";

            mtrace($client_id . " - " . $client_secret . " - " . $account_id);

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

            mtrace($client_id . " - " . $client_secret . " - " . $account_id);
            // Cierra cURL
            curl_close($ch);
            mtrace($response);
            // Procesa la respuesta
            if ($http_code === 200) {
                $response_data = json_decode($response, true);
                return $response_data['access_token'];
            } else {
                mtrace("Error $http_code: $response");
                return null;
            }
        };

        $client_id = 'b1qA5cHGSsGaE01BPfBztA';
        $client_secret = 'R433ORUTjUew4FmFwuCbz3SqTMxntt3G';
        $account_id = 'oQySkN8RTiClQmI4rbaoFA';

        $access_token_zoom = $getAccessTokenZoom($client_id, $client_secret, $account_id);
        if ($access_token_zoom) {
            mtrace("Access Token Zoom: $access_token_zoom");
        } else {
            mtrace("Error al obtener el token de acceso.");
        }

        $getLastMeetingId = function ($access_token) {
            $from = '2024-10-15';
            $to = '2024-10-29';
            $user_id = 'softwareclipresume@gmail.com';
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
            mtrace($result);

            if ($result === FALSE) {
                mtrace("ERROR " . $result);
            } else {
                $meetings = json_decode($result, true);
                $latest_meeting = end($meetings['meetings']);
                $meeting_id = $latest_meeting['id'];
                mtrace($meetings);
                mtrace($latest_meeting);
                mtrace($meeting_id);
            }
            //mtrace($meeting_id);

            return $meeting_id;
        };

        $meeting_id = $getLastMeetingId($access_token_zoom);

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
            mtrace($result);
            if ($result === FALSE) { /* Manejar error */
                mtrace('ERROR obteniendo meeting ' . $result);
            }

            $recordings = json_decode($result, true);
            mtrace($recordings);

            return $recordings;
        };

        $recordings = $getMeetingById($access_token_zoom, $meeting_id);

        $getDownloadLinks = function ($recordings) {
            $links = [
                'videos' => [],
                'transcripts' => [],
            ];

            foreach ($recordings['recording_files'] as $file) {
                if ($file['file_type'] === 'MP4') {
                    $download_url = $file['download_url'];
                    $links['videos'][] = $download_url;
                    mtrace($download_url);
                } elseif ($file['file_type'] === 'VTT') {
                    $download_url = $file['download_url'];
                    $links['transcripts'][] = $download_url;
                    mtrace($download_url);
                }
            }
            mtrace($links);
            return $links;
        };

        $download_links = $getDownloadLinks($recordings);

        // function uploadToDrive($file_url, $access_token, $file_name, $folder_id = null)
        // {
        //     mtrace("Subiendo archivo a Google Drive...");
        //     mtrace("URL: $file_url");
        //     $file_data = file_get_contents($file_url);
        //     mtrace($file_data);
        //     // Obtener el tipo MIME del archivo
        //     $mimeType = mime_content_type($file_url);

        //     $boundary = uniqid();
        //     $delimiter = "--" . $boundary;
        //     $eol = "\r\n";

        //     $metadata = [
        //         'name' => $file_name,
        //         'parents' => $folder_id ? [$folder_id] : [],  // Especifica la carpeta destino si se proporciona $folder_id
        //         'mimeType' => $mimeType
        //     ];

        //     $body = $delimiter . $eol
        //         . 'Content-Type: application/json; charset=UTF-8' . $eol . $eol
        //         . json_encode($metadata) . $eol
        //         . $delimiter . $eol
        //         . 'Content-Type: video/mp4' . $eol . $eol
        //         . $file_url . $eol
        //         . $delimiter . "--";

        //     $headers = [
        //         "Authorization: Bearer $access_token",
        //         "Content-Type: multipart/related; boundary=" . $boundary,
        //         "Content-Length: " . strlen($body),
        //     ];

        //     $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart");
        //     curl_setopt($ch, CURLOPT_POST, true);
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        //     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //     $response = curl_exec($ch);
        //     curl_close($ch);

        //     return json_decode($response, true);
        // }

        function uploadToDrive($file_url, $zoom_access_token, $drive_access_token, $file_name, $folder_id = null)
        {
            mtrace("Subiendo archivo a Google Drive...");
            mtrace("URL: $file_url");
        
            // Usa cURL para descargar el archivo desde Zoom con autenticación y seguimiento de redirecciones
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
                . 'Content-Type: video/mp4' . $eol . $eol
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
        
            return json_decode($response, true);
        }


        $links = $download_links;

        foreach ($links['videos'] as $index => $video_url) {
            $file_name = "Video_$index.mp4";
            uploadToDrive($video_url, $access_token_zoom,$access_token_drive, $file_name, "1cijCe6C10O_q-DwZACtZ-QBfB_9bvNVD");
        }

        foreach ($links['transcripts'] as $index => $transcript_url) {
            $file_name = "Transcript_$index.vtt";
            uploadToDrive($transcript_url, $access_token_zoom,$access_token_drive, $file_name, "1cijCe6C10O_q-DwZACtZ-QBfB_9bvNVD");
        }
    }
}