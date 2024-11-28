<?php
/** @var array $_SERVER */
// Incluir la biblioteca de Google Client
require_once plugin_dir_path( __DIR__ ) . 'vendor/autoload.php';

use Google\Client as Google_Client;
use Google\Service\Sheets as Google_Service_Sheets;
use Google\Service\Sheets\Resource\Spreadsheets\Values as Google_Service_Sheets_Values;
use Google\Service\Sheets\ValueRange as Google_Service_Sheets_ValueRange;

// Constante para controlar el logging
define( 'IWGS_DEBUG', true );

/**
 * Registra un mensaje en el archivo de log.
 *
 * @param string $message El mensaje a registrar
 * @param string $level El nivel de log (error, warning, info)
 * @return void
 */
function iwgs_log( string $message, string $level = 'info' ): void {
	if ( ! IWGS_DEBUG && $level !== 'error' ) {
		return;
	}

	$log_file          = WP_CONTENT_DIR . '/iwgs-error.log';
	$timestamp         = current_time( 'mysql' );
	$formatted_message = "[{$timestamp}] [{$level}] {$message}\n";
	error_log( $formatted_message, 3, $log_file );
}

/**
 * Añade un menú de administración para el plugin.
 * @return void
 */
function iwgs_add_admin_menu(): void {
	add_options_page(
		'Integración WPOptin -> Google Sheets',
		'Integración WPOptin -> Google Sheets',
		'manage_options',
		'iwgs',
		'iwgs_options_page'
	);
}

add_action( 'admin_menu', 'iwgs_add_admin_menu' );

/**
 * Inicializa la configuración del plugin.
 * @return void
 */
function iwgs_settings_init(): void {
	register_setting( 'iwgs', 'iwgs_settings' );

	add_settings_section(
		'iwgs_section_developers',
		__( 'Configuraciones del Plugin', 'iwgs' ),
		'iwgs_settings_section_callback',
		'iwgs'
	);

	add_settings_field(
		'iwgs_google_client_id',
		__( 'Client ID de Google', 'iwgs' ),
		'iwgs_google_client_id_render',
		'iwgs',
		'iwgs_section_developers'
	);

	add_settings_field(
		'iwgs_google_client_secret',
		__( 'Client Secret de Google', 'iwgs' ),
		'iwgs_google_client_secret_render',
		'iwgs',
		'iwgs_section_developers'
	);

	add_settings_field(
		'iwgs_spreadsheet_id',
		__( 'ID de la Hoja de Google', 'iwgs' ),
		'iwgs_spreadsheet_id_render',
		'iwgs',
		'iwgs_section_developers'
	);

	add_settings_field(
		'iwgs_sheet_name',
		__( 'Nombre de la Hoja de Google', 'iwgs' ),
		'iwgs_sheet_name_render',
		'iwgs',
		'iwgs_section_developers'
	);
}

add_action( 'admin_init', 'iwgs_settings_init' );

/**
 * Renderiza la página de opciones del plugin.
 * @return void
 */
function iwgs_options_page(): void {
	$options = get_option( 'iwgs_settings' );
	$client  = new Google_Client();
	$client->setClientId( $options['iwgs_google_client_id'] ?? '' );
	$client->setClientSecret( $options['iwgs_google_client_secret'] ?? '' );
	$client->setRedirectUri( admin_url( 'options-general.php?page=iwgs' ) );
	$client->addScope( Google_Service_Sheets::SPREADSHEETS );
	$client->setAccessType( 'offline' );
	$client->setPrompt( 'consent' );

	$is_authorized = false;
	$needs_reauth  = false;

	if ( isset( $options['iwgs_access_token'] ) ) {
		$client->setAccessToken( $options['iwgs_access_token'] );
		if ( ! $client->isAccessTokenExpired() ) {
			$is_authorized = true;
		} elseif ( isset( $options['iwgs_refresh_token'] ) ) {
			try {
				$client->fetchAccessTokenWithRefreshToken( $options['iwgs_refresh_token'] );
				$new_token                    = $client->getAccessToken();
				$options['iwgs_access_token'] = $new_token;
				update_option( 'iwgs_settings', $options );
				$is_authorized = true;
			} catch ( Exception $e ) {
				iwgs_log( "Error al refrescar el token: " . $e->getMessage(), 'error' );
				$needs_reauth = true;
			}
		} else {
			$needs_reauth = true;
		}
	} else {
		$needs_reauth = true;
	}

	?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action='options.php' method='post'>
			<?php
			settings_fields( 'iwgs' );
			do_settings_sections( 'iwgs' );

			echo '<div style="display: flex; gap: 10px; margin-top: 20px;">';
			submit_button( __( 'Guardar cambios', 'iwgs' ), 'primary', 'submit', false );

			if ( $needs_reauth || ! $is_authorized ) {
				$authUrl = $client->createAuthUrl();
				echo '<a class="button button-secondary" href="' . esc_url( $authUrl ) . '">' . __( 'Reautorizar con Google', 'iwgs' ) . '</a>';
				echo '</div>';
				echo '<p class="description">' . __( 'Es necesario reautorizar el acceso a Google Sheets para que el plugin funcione correctamente.', 'iwgs' ) . '</p>';
			} else {
				echo '<a class="button button-secondary" href="#" onclick="return false;" disabled>' . __( 'Autorizado con Google', 'iwgs' ) . '</a>';
				echo '</div>';
				echo '<p class="description">' . __( 'El plugin está correctamente autorizado con Google Sheets.', 'iwgs' ) . '</p>';
			}
			?>
        </form>
        <hr>
        <h2><?php _e( 'Webhook URL para WPOptin', 'iwgs' ); ?></h2>
        <p><?php _e( 'Use la siguiente URL en la configuración de su ruleta en WPOptin:', 'iwgs' ); ?></p>
        <code><?php echo esc_url( rest_url( 'iwgs/v1/webhook' ) ); ?></code>
    </div>
	<?php

	if ( $needs_reauth || ! $is_authorized ) {
		$authUrl = $client->createAuthUrl();
		echo '<a class="button button-secondary" href="' . esc_url( $authUrl ) . '">' . __( 'Reautorizar con Google', 'iwgs' ) . '</a>';
		echo '</div>';
		echo '<p class="description">' . __( 'Es necesario reautorizar el acceso a Google Sheets para que el plugin funcione correctamente.', 'iwgs' ) . '</p>';
	} else {
		echo '<a class="button button-secondary" href="#" onclick="return false;" disabled>' . __( 'Autorizado con Google', 'iwgs' ) . '</a>';
		echo '</div>';
		echo '<p class="description">' . __( 'El plugin está correctamente autorizado con Google Sheets.', 'iwgs' ) . '</p>';
	}
}

function iwgs_google_client_id_render(): void {
	$options = get_option( 'iwgs_settings' );
	?>
    <input type='text'
           name='iwgs_settings[iwgs_google_client_id]'
           id='iwgs_settings[iwgs_google_client_id]'
           class='iwgs-input'
           value='<?php echo isset( $options['iwgs_google_client_id'] ) ? esc_attr( $options['iwgs_google_client_id'] ) : ''; ?>'>
	<?php
}

function iwgs_google_client_secret_render(): void {
	$options = get_option( 'iwgs_settings' );
	?>
    <input type='text'
           name='iwgs_settings[iwgs_google_client_secret]'
           id='iwgs_settings[iwgs_google_client_secret]'
           class='iwgs-input'
           value='<?php echo isset( $options['iwgs_google_client_secret'] ) ? esc_attr( $options['iwgs_google_client_secret'] ) : ''; ?>'>
	<?php
}

function iwgs_spreadsheet_id_render(): void {
	$options = get_option( 'iwgs_settings' );
	?>
    <input type='text'
           name='iwgs_settings[iwgs_spreadsheet_id]'
           id='iwgs_settings[iwgs_spreadsheet_id]'
           class='iwgs-input'
           value='<?php echo isset( $options['iwgs_spreadsheet_id'] ) ? esc_attr( $options['iwgs_spreadsheet_id'] ) : ''; ?>'>
	<?php
}

function iwgs_sheet_name_render(): void {
	$options = get_option( 'iwgs_settings' );
	?>
    <input type='text'
           name='iwgs_settings[iwgs_sheet_name]'
           id='iwgs_settings[iwgs_sheet_name]'
           value='<?php echo isset( $options['iwgs_sheet_name'] ) ? esc_attr( $options['iwgs_sheet_name'] ) : 'Sheet1'; ?>'>
	<?php
}

function iwgs_settings_section_callback(): void {
	echo __( 'Introduce tus configuraciones para integrar WPOptin con Google Sheets.', 'iwgs' );
}

/**
 * Maneja el código de autenticación de OAuth.
 * @return void
 */
function iwgs_handle_oauth(): void {
	if ( isset( $_GET['code'] ) ) {
		$options = get_option( 'iwgs_settings' );
		$client  = new Google_Client();
		$client->setClientId( $options['iwgs_google_client_id'] );
		$client->setClientSecret( $options['iwgs_google_client_secret'] );
		$client->setRedirectUri( admin_url( 'options-general.php?page=iwgs' ) );
		$client->addScope( Google_Service_Sheets::SPREADSHEETS );

		try {
			$token = $client->fetchAccessTokenWithAuthCode( $_GET['code'] );
			$client->setAccessToken( $token );

			// Asegurarnos de guardar el refresh token
			$options['iwgs_access_token'] = $token;
			if ( isset( $token['refresh_token'] ) ) {
				$options['iwgs_refresh_token'] = $token['refresh_token'];
			}

			update_option( 'iwgs_settings', $options );
			iwgs_log( "Token de acceso y refresh token guardados con éxito" );

			// Después de una autorización exitosa, procesar datos pendientes
			iwgs_process_pending_data();
		} catch ( Exception $e ) {
			iwgs_log( "Error al obtener el token de acceso: " . $e->getMessage(), 'error' );
		}

		wp_redirect( admin_url( 'options-general.php?page=iwgs' ) );
		exit;
	}
}

add_action( 'admin_init', 'iwgs_handle_oauth' );

/**
 * Procesa los datos pendientes de envío a Google Sheets
 * después de que se haya reautorizado el plugin.
 * @return void
 */
function iwgs_process_pending_data(): void {
	$pending_file_path = WP_CONTENT_DIR . '/iwgs_pending_data.csv';
	if ( ! file_exists( $pending_file_path ) ) {
		iwgs_log( "No hay datos pendientes para procesar" );

		return;
	}

	$client = iwgs_ensure_authorized_client();
	if ( ! $client ) {
		iwgs_log( "No se pudo obtener un cliente autorizado para procesar datos pendientes", 'error' );

		return;
	}

	$pending_data  = array_map( 'str_getcsv', file( $pending_file_path ) );
	$headers       = array_shift( $pending_data ); // Remover la fila de encabezados
	$success_count = 0;
	$failure_count = 0;

	foreach ( $pending_data as $row ) {
		try {
			iwgs_send_to_google_sheets( $row );
			$success_count ++;
		} catch ( Exception $e ) {
			iwgs_log( "Error al enviar datos pendientes a Google Sheets: " . $e->getMessage(), 'error' );
			$failure_count ++;
		}
	}

	if ( $failure_count === 0 ) {
		// Si todos los datos se enviaron con éxito, eliminar el archivo de datos pendientes
		unlink( $pending_file_path );
		iwgs_log( "Todos los datos pendientes procesados y enviados. Archivo temporal eliminado." );
	} else {
		// Si hubo fallos, mantener solo los datos que fallaron en el archivo
		$failed_data = array_slice( $pending_data, $success_count );
		file_put_contents( $pending_file_path, '' );
		$csv_file = fopen( $pending_file_path, 'w' );
		fputcsv( $csv_file, $headers ); // Volver a escribir los encabezados
		foreach ( $failed_data as $row ) {
			fputcsv( $csv_file, $row );
		}
		fclose( $csv_file );
		iwgs_log( "Procesamiento de datos pendientes completado. Éxitos: $success_count, Fallos: $failure_count" );
	}
}

/**
 * Muestra un aviso en el panel de administración si no se ha autorizado la aplicación.
 * @return void
 */
function iwgs_display_oauth_notice(): void {
	$options = get_option( 'iwgs_settings' );
	if ( empty( $options['iwgs_access_token'] ) && ! empty( $options['iwgs_google_client_id'] ) && ! empty( $options['iwgs_google_client_secret'] ) ) {
		$client = new Google_Client();
		$client->setClientId( $options['iwgs_google_client_id'] );
		$client->setClientSecret( $options['iwgs_google_client_secret'] );
		$client->setRedirectUri( admin_url( 'options-general.php?page=iwgs' ) );
		$client->addScope( Google_Service_Sheets::SPREADSHEETS );
		$authUrl = $client->createAuthUrl();
		echo '<div class="notice notice-warning"><p>';
		echo sprintf( __( 'Para usar el plugin, <a href="%s">haz clic aquí para autorizar la aplicación</a>.', 'iwgs' ), esc_url( $authUrl ) );
		echo '</p></div>';
	}
}

add_action( 'admin_notices', 'iwgs_display_oauth_notice' );

/**
 * Refresca el token de Google si es necesario.
 * @return bool
 */
function iwgs_refresh_google_token(): bool {
	$options = get_option( 'iwgs_settings' );
	$client  = new Google_Client();
	$client->setClientId( $options['iwgs_google_client_id'] );
	$client->setClientSecret( $options['iwgs_google_client_secret'] );

	$refreshToken = $options['iwgs_refresh_token'] ?? null;
	$accessToken  = $options['iwgs_access_token'] ?? null;

	iwgs_log( "Refresh Token: " . ( $refreshToken ? 'Presente' : 'Ausente' ) );
	iwgs_log( "Access Token: " . ( $accessToken ? 'Presente' : 'Ausente' ) );

	if ( $refreshToken ) {
		$client->refreshToken( $refreshToken );
	} else {
		iwgs_log( "No hay refresh token disponible", 'error' );

		return false;
	}

	if ( $accessToken ) {
		$client->setAccessToken( $accessToken );
	}

	if ( $client->isAccessTokenExpired() ) {
		iwgs_log( "Token de acceso expirado, intentando refrescar" );
		try {
			$newAccessToken = $client->fetchAccessTokenWithRefreshToken( $refreshToken );
			if ( isset( $newAccessToken['access_token'] ) ) {
				$options['iwgs_access_token'] = $newAccessToken;
				update_option( 'iwgs_settings', $options );
				iwgs_log( "Token de acceso actualizado con éxito" );

				return true;
			} else {
				iwgs_log( "No se pudo obtener un nuevo access token", 'error' );

				return false;
			}
		} catch ( Exception $e ) {
			iwgs_log( "Error al actualizar el token de acceso: " . $e->getMessage(), 'error' );

			return false;
		}
	} else {
		iwgs_log( "El token de acceso aún es válido" );

		return true;
	}
}

/**
 * Obtiene un cliente de Google autenticado.
 * @return Google_Client|null
 */
function iwgs_get_google_client(): ?Google_Client {
	$options = get_option( 'iwgs_settings' );
	$client  = new Google_Client();
	$client->setClientId( $options['iwgs_google_client_id'] );
	$client->setClientSecret( $options['iwgs_google_client_secret'] );
	$client->setRedirectUri( admin_url( 'options-general.php?page=iwgs' ) );
	$client->addScope( Google_Service_Sheets::SPREADSHEETS );

	if ( isset( $options['iwgs_access_token'] ) ) {
		$client->setAccessToken( $options['iwgs_access_token'] );

		if ( $client->isAccessTokenExpired() ) {
			iwgs_log( "Token de acceso expirado, intentando refrescar" );
			if ( isset( $options['iwgs_refresh_token'] ) ) {
				try {
					$client->fetchAccessTokenWithRefreshToken( $options['iwgs_refresh_token'] );
					$newAccessToken               = $client->getAccessToken();
					$options['iwgs_access_token'] = $newAccessToken;
					update_option( 'iwgs_settings', $options );
					iwgs_log( "Token de acceso actualizado con éxito" );
				} catch ( Exception $e ) {
					iwgs_log( "Error al refrescar el token: " . $e->getMessage(), 'error' );

					return null;
				}
			} else {
				iwgs_log( "No hay refresh token disponible", 'error' );

				return null;
			}
		}
	} else {
		iwgs_log( "No hay token de acceso disponible", 'error' );

		return null;
	}

	return $client;
}

/**
 * Procesa los datos recibidos del webhook y los envía a Google Sheets.
 *
 * Esta función se encarga de recibir los datos del webhook, procesarlos y
 * enviarlos a la hoja de Google configurada.
 * @var array @_SERVER
 * @return void
 */
function iwgs_process_webhook_data(): void {
	iwgs_log( "Webhook recibido" );

	if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
		iwgs_log( "Método no permitido: " . $_SERVER['REQUEST_METHOD'], 'error' );
		http_response_code( 405 );
		echo json_encode( [ 'status' => 'error', 'message' => 'Método no permitido' ], JSON_THROW_ON_ERROR );
		exit;
	}

	$raw_data = file_get_contents( "php://input" );
	iwgs_log( "Datos recibidos: " . $raw_data );

	parse_str( $raw_data, $data );

	if ( empty( $data ) ) {
		iwgs_log( "Datos inválidos o vacíos", 'error' );
		http_response_code( 400 );
		echo json_encode( [ 'status' => 'error', 'message' => 'Datos inválidos o vacíos' ], JSON_THROW_ON_ERROR );
		exit;
	}

	$mapped_data = [
		$data['timestamp'] ?? '',
		$data['email'] ?? '',
		$data['fields'][0]['value'] ?? '',
		$data['fields'][1]['value'] ?? '',
		$data['winning'] ?? '',
		$data['segment_text'] ?? ''
	];

	// Verificar si todos los campos están vacíos
	if ( empty( array_filter( $mapped_data ) ) ) {
		iwgs_log( "Todos los campos están vacíos", 'error' );
		http_response_code( 400 );
		echo json_encode( [ 'status' => 'error', 'message' => 'Todos los campos están vacíos' ], JSON_THROW_ON_ERROR );
		exit;
	}

	iwgs_log( "Datos mapeados: " . print_r( $mapped_data, true ) );

	// Guardar en el archivo de respaldo general (solo una vez)
	iwgs_save_to_backup_csv( $mapped_data );

	// Verificar y renovar la autorización antes de enviar los datos
	$client = iwgs_ensure_authorized_client();

	if ( $client ) {
		try {
			iwgs_send_to_google_sheets( $mapped_data );
			iwgs_log( "Datos procesados y enviados a Google Sheets con éxito" );
			http_response_code( 200 );
			echo json_encode( [
				'status'  => 'success',
				'message' => 'Datos procesados y enviados a Google Sheets'
			], JSON_THROW_ON_ERROR );
		} catch ( Exception $e ) {
			iwgs_log( "Error al enviar datos a Google Sheets: " . $e->getMessage(), 'error' );
			iwgs_save_to_pending_csv( $mapped_data );
			iwgs_notify_admin_auth_required();
			http_response_code( 500 );
			echo json_encode( [ 'status'  => 'error',
			                    'message' => 'Error al procesar los datos, guardados localmente'
			], JSON_THROW_ON_ERROR );
		}
	} else {
		iwgs_log( "No se pudo obtener un cliente autorizado", 'error' );
		iwgs_save_to_pending_csv( $mapped_data );
		iwgs_notify_admin_auth_required();
		http_response_code( 500 );
		echo json_encode( [ 'status'  => 'error',
		                    'message' => 'Error de autorización, datos guardados localmente'
		], JSON_THROW_ON_ERROR );
	}
	exit;
}

/**
 * Guarda los datos de resultados en un archivo CSV
 * localmente como backup.
 *
 * @param array $data Los datos a guardar.
 * @return void
 */
function iwgs_save_to_backup_csv( array $data ): void {
	$csv_file_path = WP_CONTENT_DIR . '/iwgs_results.csv';
	$file_exists   = file_exists( $csv_file_path );
	$csv_file      = fopen( $csv_file_path, 'a' );
	if ( ! $file_exists ) {
		fputcsv( $csv_file, [ 'Timestamp', 'Email', 'Nombre', 'Ganó', 'Premio' ] );
		iwgs_log( "Archivo de respaldo creado con encabezados" );
	}
	fputcsv( $csv_file, $data );
	fclose( $csv_file );
	iwgs_log( "Datos guardados en el archivo de respaldo general" );
}

/**
 * Guarda localmente los datos pendientes de enviar
 * a Google Sheets, para su posterior reenvío.
 *
 * @param array $data Los datos a guardar.
 * @return void
 */
function iwgs_save_to_pending_csv( array $data ): void {
	$csv_file_path = WP_CONTENT_DIR . '/iwgs_pending_data.csv';
	$file_exists   = file_exists( $csv_file_path );
	$csv_file      = fopen( $csv_file_path, 'a' );
	if ( ! $file_exists ) {
		fputcsv( $csv_file, [ 'Timestamp', 'Email', 'Nombre', 'Ganó', 'Premio' ] );
		iwgs_log( "Archivo de datos pendientes creado con encabezados" );
	}
	fputcsv( $csv_file, $data );
	fclose( $csv_file );
	iwgs_log( "Datos guardados en el archivo de datos pendientes" );
}

/**
 * Asegura que haya un cliente de Google Sheets autorizado.
 *
 * @return Google_Client|null
 */
function iwgs_ensure_authorized_client(): ?Google_Client {
	$options = get_option( 'iwgs_settings' );
	$client  = new Google_Client();
	$client->setClientId( $options['iwgs_google_client_id'] );
	$client->setClientSecret( $options['iwgs_google_client_secret'] );
	$client->setRedirectUri( admin_url( 'options-general.php?page=iwgs' ) );
	$client->addScope( Google_Service_Sheets::SPREADSHEETS );

	if ( isset( $options['iwgs_access_token'] ) ) {
		$client->setAccessToken( $options['iwgs_access_token'] );

		if ( $client->isAccessTokenExpired() ) {
			iwgs_log( "Token de acceso expirado, intentando refrescar" );
			if ( isset( $options['iwgs_refresh_token'] ) ) {
				try {
					$client->fetchAccessTokenWithRefreshToken( $options['iwgs_refresh_token'] );
					$newAccessToken               = $client->getAccessToken();
					$options['iwgs_access_token'] = $newAccessToken;
					update_option( 'iwgs_settings', $options );
					iwgs_log( "Token de acceso actualizado con éxito" );

					return $client;
				} catch ( Exception $e ) {
					iwgs_log( "Error al refrescar el token: " . $e->getMessage(), 'error' );

					return null;
				}
			} else {
				iwgs_log( "No hay refresh token disponible", 'error' );

				return null;
			}
		}

		return $client;
	} else {
		iwgs_log( "No hay token de acceso disponible", 'error' );

		return null;
	}
}

/**
 * Envía los datos del formulario a Google Sheets.
 * @param array $data Los datos a enviar.
 * @param int $retryCount El número de intentos de reenvío.
 * @return void
 */
function iwgs_send_to_google_sheets( array $data, $retryCount = 0 ): void {
	$maxRetries = 3;
	$client     = iwgs_ensure_authorized_client();

	if ( ! $client ) {
		iwgs_log( "No se pudo obtener un cliente de Google autenticado", 'error' );
		iwgs_notify_admin_auth_required();

		return;
	}

	try {
		$service       = new Google_Service_Sheets( $client );
		$options       = get_option( 'iwgs_settings' );
		$spreadsheetId = $options['iwgs_spreadsheet_id'];
		$sheetName     = $options['iwgs_sheet_name'] ?? 'Lista';

		$range  = $sheetName . '!A:F';
		$body   = new Google_Service_Sheets_ValueRange( [
			'values' => [ $data ]
		] );
		$params = [
			'valueInputOption' => 'USER_ENTERED',
			'insertDataOption' => 'INSERT_ROWS'
		];

		$result       = $service->spreadsheets_values->append( $spreadsheetId, $range, $body, $params );
		$updatedRange = $result->getUpdates()->getUpdatedRange();
		preg_match( '/(\d+)$/', $updatedRange, $matches );
		$newRow = $matches[1] ?? 'desconocida';
		iwgs_log( "Datos enviados a Google Sheets con éxito. Fila: " . $newRow );
	} catch ( Google_Service_Exception $e ) {
		$error = json_decode( $e->getMessage() );
		if ( $error->error->status == 'RESOURCE_EXHAUSTED' && $retryCount < $maxRetries ) {
			$waitTime = pow( 2, $retryCount ) * 1000000; // Espera exponencial en microsegundos
			usleep( $waitTime );
			iwgs_log( "Límite de API alcanzado. Reintentando en " . ( $waitTime / 1000000 ) . " segundos." );
			iwgs_send_to_google_sheets( $data, $retryCount + 1 );
		} else {
			iwgs_log( "Error al enviar datos a Google Sheets: " . $e->getMessage(), 'error' );
			iwgs_notify_admin_auth_required();
		}
	} catch ( Exception $e ) {
		iwgs_log( "Error al enviar datos a Google Sheets: " . $e->getMessage(), 'error' );
		iwgs_notify_admin_auth_required();
	}
}

/**
 * Notifica al administrador cuando se requiere reautorización.
 * @return void
 */
function iwgs_notify_admin_auth_required(): void {
	$admin_email = get_option( 'admin_email' );
	$subject     = "Acción requerida: Reautorizar IWGS con Google Sheets";

	$pending_file_path = WP_CONTENT_DIR . '/iwgs_pending_data.csv';
	$pending_file_url  = content_url( 'iwgs_pending_data.csv' );
	$backup_file_url   = content_url( 'iwgs_results.csv' );

	$message = "
        <p>La autorización con Google Sheets ha expirado. Por favor, reautoriza el plugin 
        <a href='https://dc.ncdigital.net/wp-admin/options-general.php?page=iwgs'><strong>IWGS en el panel de administración</strong></a>.</p>

        <p>Hay datos pendientes de envío a Google Sheets. Estos datos se procesarán automáticamente después de la reautorización.</p>

        <p>Archivos de datos:</p>
        <ul>
            <li>Datos pendientes: <a href='{$pending_file_url}'>{$pending_file_url}</a></li>
            <li>Respaldo general: <a href='{$backup_file_url}'>{$backup_file_url}</a></li>
        </ul>

        <p>Este mensaje se enviará cada vez que se reciba un envío de formulario hasta que se reautorice el plugin.</p>

        <p>Saludos,</p>
        <p>El equipo de soporte de NC Digital</p>
    ";

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	$result = wp_mail( $admin_email, $subject, $message, $headers );

	if ( $result ) {
		iwgs_log( "Notificación de reautorización enviada al administrador", 'info' );
	} else {
		iwgs_log( "Error al enviar la notificación de reautorización", 'error' );
		global $phpmailer;
		if ( isset( $phpmailer ) && $phpmailer->ErrorInfo ) {
			iwgs_log( "Error de PHPMailer: " . $phpmailer->ErrorInfo, 'error' );
		}
	}
}

/**
 * Registra la ruta de la API REST para el webhook.
 * @return void
 */
add_action( 'rest_api_init',
    function () {
	register_rest_route( 'iwgs/v1', '/webhook', [
		'methods'             => 'POST',
		'callback'            => 'iwgs_process_webhook_data',
		'permission_callback' => '__return_true'
	] );
} );

?>