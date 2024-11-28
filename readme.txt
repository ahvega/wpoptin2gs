=== Integración WPOptin -> Google Sheets ===
Contributors: Adalberto Hernández Vega
Donate link: http://example.com/
Tags: WPOptin, Google Sheets, Integración, Datos de Formulario, Automatización
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Descripción ==

Integración WPOptin -> Google Sheets permite integrar fácilmente WP Optin con Google Sheets para enviar datos de la ruleta automáticamente a una hoja de cálculo de Google. Este plugin ofrece una solución robusta y confiable para la captura y almacenamiento de datos de participantes.

Características principales:

1. Integración automática de datos entre WPOptin y Google Sheets.
2. Autenticación segura con Google utilizando OAuth 2.0.
3. Manejo automático de renovación de tokens de acceso.
4. Almacenamiento local de datos como respaldo.
5. Sistema de cola para datos pendientes en caso de fallos de conexión.
6. Notificaciones por correo electrónico al administrador en caso de problemas de autorización.
7. Interfaz de administración para configuración fácil.
8. Logging detallado para facilitar el debugging.

== Instalación ==

1. Sube la carpeta `integracion-wpoptin-google-sheets` al directorio `/wp-content/plugins/` o instala el plugin directamente desde la página de plugins de WordPress.
2. Activa el plugin a través de la página 'Plugins' en WordPress.
3. Ve a 'Ajustes' -> 'Integración WPOptin -> Google Sheets' para configurar el plugin.
4. Introduce tus credenciales de Google API, el ID de la hoja de Google Sheets y el nombre de la hoja.
5. Autentica la aplicación con Google cuando se te solicite.

== Preguntas Frecuentes ==

= ¿Cómo obtengo el Client ID y el Client Secret de Google? =

Para obtener el Client ID y el Client Secret, sigue estos pasos:
1. Ve a la [Google Cloud Console](https://console.developers.google.com/).
2. Crea un nuevo proyecto o selecciona un proyecto existente.
3. Habilita las APIs de Google Sheets y Google Drive.
4. Ve a 'Credenciales' y crea un ID de cliente OAuth 2.0.
5. Descarga las credenciales y copia el Client ID y el Client Secret en la configuración del plugin.

= ¿Cómo obtengo el ID de la hoja de Google Sheets? =

El ID de la hoja de Google Sheets se encuentra en la URL de tu hoja de cálculo. Por ejemplo, en `https://docs.google.com/spreadsheets/d/1A2B3C4D5E6F7G8H9I0J/edit`, el ID es `1A2B3C4D5E6F7G8H9I0J`.

= ¿Qué sucede si hay un problema de conexión con Google Sheets? =

El plugin guarda automáticamente los datos en un archivo CSV local como respaldo. Cuando se restablece la conexión, los datos pendientes se envían automáticamente a Google Sheets.

= ¿Cómo sé si hay problemas con la autorización? =

El plugin enviará una notificación por correo electrónico al administrador del sitio si hay problemas de autorización. También puedes revisar los logs del plugin para obtener información detallada.

== Capturas de Pantalla ==

1. Configuración del plugin en el administrador de WordPress.
2. Proceso de autenticación OAuth2 con Google.
3. Datos enviados desde WP Optin a Google Sheets.

== Changelog ==

= 1.0 =
* Versión inicial del plugin.
* Implementación de la integración básica con Google Sheets.
* Sistema de autenticación OAuth2.
* Almacenamiento local de datos como respaldo.
* Sistema de cola para datos pendientes.
* Notificaciones por correo electrónico.
* Logging para debugging.

== Upgrade Notice ==

= 1.0 =
Versión inicial del plugin con todas las funcionalidades básicas implementadas.

== Licencia ==

Este plugin está licenciado bajo la GPLv2 o posterior.
Para más detalles, visita [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html).