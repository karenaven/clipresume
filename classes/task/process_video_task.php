<?php
namespace mod_clipresume\task;

defined('MOODLE_INTERNAL') || die();

class process_video_task extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('process_video_task', 'clipresume');
    }

    public function execute()
    {
        global $DB, $CFG;

        //INICIO Google Drive
        //$credentials_path = $CFG->dirroot . '\local\clipresume\path_to_google_credentials.json';


        // Obtén el archivo JSON desde el área de configuración.
        $fs = get_file_storage();
        $context = \context_system::instance();
        $files = $fs->get_area_files($context->id, 'mod_clipresume', 'credentials_path', 0, 'itemid, filepath, filename', false);

        // Verifica si el archivo existe.
        if (count($files) > 0) {
            // Obtiene el primer archivo (asumiendo que solo hay un archivo JSON subido).
            $file = reset($files);
            $jsoncontent = $file->get_content();
            $credentials_path = json_decode($jsoncontent, true);

            if (!$credentials_path) {
                echo "Error al leer el contenido del archivo JSON.";
            }
        } else {
            echo "No se encontró el archivo JSON de credenciales.";
        }

        // $credentials_path = get_config('mod_clipresume', 'credentials_path');
        $folder_id = get_config('mod_clipresume', 'drive_folder_id');
        $client_id = get_config('mod_clipresume', 'zoom_client_id');
        $client_secret = get_config('mod_clipresume', 'zoom_client_secret');
        $account_id = get_config('mod_clipresume', 'zoom_account_id');
        $user_id = get_config('mod_clipresume', 'zoom_user_id');

        // // Leer credenciales
        // $leer_credenciales = function ($credentials_path) {
        //     mtrace("Leyendo credenciales...");
        //     $credentials = json_decode(file_get_contents($credentials_path), true);
        //     if (!$credentials) {
        //         mtrace("Error al leer las credenciales desde $credentials_path");
        //         return false;
        //     }
        //     mtrace("Credenciales leídas correctamente.");
        //     return $credentials;
        // };
        $leer_credenciales = function ($credentials_path) {
            mtrace("Leyendo credenciales...");
            if (!$credentials_path) {
                mtrace("Error al leer las credenciales.");
                return false;
            }
            mtrace("Credenciales leídas correctamente.");
            return $credentials_path;
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
           
            // Procesa la respuesta
            if ($http_code === 200) {
                $response_data = json_decode($response, true);
                return $response_data['access_token'];
            } else {
                mtrace("Error $http_code: $response");
                return null;
            }
        };

        // $client_id = 'b1qA5cHGSsGaE01BPfBztA';
        // $client_secret = 'R433ORUTjUew4FmFwuCbz3SqTMxntt3G';
        // $account_id = 'oQySkN8RTiClQmI4rbaoFA';

        $access_token_zoom = $getAccessTokenZoom($client_id, $client_secret, $account_id);
        if ($access_token_zoom) {
            mtrace("Access Token Zoom: $access_token_zoom");
        } else {
            mtrace("Error al obtener el token de acceso.");
        }

        $getLastMeetingId = function ($access_token, $user_id) {
            $from = '2024-10-15';
            $to = '2024-10-29';
            // $from = date('Y-m-d', strtotime('yesterday'));
            // $to = date('Y-m-d');
            // $user_id = 'softwareclipresume@gmail.com';
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
                    // $download_url = $file['download_url'];
                    // $links['videos'][] = $download_url;
                    $links['videos'][] = $file;
                    mtrace($download_url);
                } elseif ($file['file_type'] === 'TRANSCRIPT') {
                    $download_url = $file['download_url'];
                    $links['transcripts'][] = $download_url;
                    mtrace($download_url);
                }
            }
            mtrace($links);
            return $links;
        };

        $download_links = $getDownloadLinks($recordings);

        
        // $getMeetingIdsFromYesterday = function ($access_token, $user_id) {
        //     $from = date('Y-m-d', strtotime('yesterday'));
        //     $to = date('Y-m-d', strtotime('yesterday'));

        //     $url = "https://api.zoom.us/v2/users/$user_id/recordings?from=" . urlencode($from) . "&to=" . urlencode($to);

        //     mtrace("Obteniendo IDs de reuniones del día anterior...");

        //     $options = array(
        //         'http' => array(
        //             'header' => "Authorization: Bearer $access_token\r\n",
        //             'method' => 'GET',
        //         ),
        //     );
        //     $meeting_ids = [];
        //     $context = stream_context_create($options);
        //     $result = file_get_contents($url, false, $context);

        //     if ($result === FALSE) {
        //         mtrace("ERROR " . $result);
        //     } else {
        //         $meetings = json_decode($result, true);

        //         if (!empty($meetings['meetings'])) {
        //             foreach ($meetings['meetings'] as $meeting) {
        //                 $meeting_ids[] = $meeting['id'];
        //             }
        //         }

        //         mtrace("Reuniones encontradas: " . json_encode($meeting_ids));
        //     }

        //     return $meeting_ids;
        // };

        // $meeting_ids = $getMeetingIdsFromYesterday($access_token_zoom, $user_id);

        // $getMeetingsByIds = function ($access_token, $meeting_ids) {
        //     $all_recordings = [];

        //     foreach ($meeting_ids as $meeting_id) {
        //         $url = "https://api.zoom.us/v2/meetings/$meeting_id/recordings";

        //         $options = array(
        //             'http' => array(
        //                 'header' => "Authorization: Bearer $access_token\r\n",
        //                 'method' => 'GET',
        //             ),
        //         );

        //         $context = stream_context_create($options);
        //         $result = file_get_contents($url, false, $context);
        //         mtrace("Obteniendo grabaciones para la reunión ID: $meeting_id");

        //         if ($result === FALSE) {
        //             mtrace('ERROR obteniendo grabaciones para la reunión ID ' . $meeting_id);
        //             continue;
        //         }

        //         $recordings = json_decode($result, true);
        //         if (!empty($recordings)) {
        //             $all_recordings[$meeting_id] = $recordings;
        //         }
        //     }

        //     return $all_recordings;
        // };

        // $recordings = $getMeetingsByIds($access_token_zoom, $meeting_ids);

        // $getDownloadLinks = function ($all_recordings) {
        //     $all_links = [
        //         'videos' => [],
        //         'transcripts' => [],
        //     ];

        //     foreach ($all_recordings as $meeting_id => $recordings) {
        //         mtrace("Procesando grabaciones para la reunión ID: $meeting_id");

        //         foreach ($recordings['recording_files'] as $file) {
        //             if ($file['file_type'] === 'MP4') {
        //                 $all_links['videos'][] = [
        //                     'meeting_id' => $meeting_id,
        //                     'file' => $file,
        //                 ];
        //                 mtrace("Video encontrado para reunión $meeting_id");
        //             } elseif ($file['file_type'] === 'TRANSCRIPT') {
        //                 $all_links['transcripts'][] = [
        //                     'meeting_id' => $meeting_id,
        //                     'download_url' => $file['download_url'],
        //                 ];
        //                 mtrace("Transcripción encontrada para reunión $meeting_id");
        //             }
        //         }
        //     }

        //     mtrace("Enlaces procesados: " . json_encode($all_links));
        //     return $all_links;
        // };

        // $download_links = $getDownloadLinks($recordings);

        function downloadFile($file_url, $zoom_access_token)
        {
            mtrace("Descargando archivo desde Zoom...");
            mtrace("URL: $file_url");

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

            return json_decode($response, true);
        }

        function uploadToDrive($file_url, $zoom_access_token, $drive_access_token, $file_name, $folder_id, $file_type)
        {
            // Descargar el archivo desde Zoom
            $file_data = downloadFile($file_url, $zoom_access_token);
            if ($file_data === null) {
                return null; // Salir si hubo un error en la descarga
            }

            // Subir el archivo a la carpeta ya creada
            return uploadFileToDrive($file_data, $file_name, $file_type, $drive_access_token, $folder_id);
        }

        // Crear la carpeta una vez antes de subir los archivos
        $folder_id = createFolder($access_token_drive, $folder_id);
        if ($folder_id === false) {
            mtrace("No se pudo crear la carpeta. Terminando.");
            exit; // Salir si hubo un error al crear la carpeta
        }

        // Ciclo para subir videos
        $links = $download_links;

        $index_clip = 0;
        foreach ($links['videos'] as $index => $video) {
            $download_url = $video['download_url'];
            $file_name = 'Video_' . $index . '.mp4';
            if (isset($video['encryption_fingerprint'])) {
                $index_clip++;
                $file_name = 'Clip_' . $index_clip . ' - ' . $video['recording_start'] . '.mp4';
            } else {
                $index++;
                $file_name = 'Video Completo_' . $index . ' - ' . $video['recording_start'] . '.mp4';
            }
            uploadToDrive($download_url, $access_token_zoom, $access_token_drive, $file_name, $folder_id, "video/mp4");
        }

        // Ciclo para subir transcripciones
        foreach ($links['transcripts'] as $index => $transcript_url) {
            $index++;
            mtrace("Subiendo Transcripciones a Google Drive...");
            $file_name = "Transcript_" . $index . ".vtt";
            uploadToDrive($transcript_url, $access_token_zoom, $access_token_drive, $file_name, $folder_id, "text/vtt");
        }

    }
}