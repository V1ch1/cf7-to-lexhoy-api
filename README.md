# Contact Form 7 to LexHoy API

Plugin de WordPress que envía automáticamente los envíos de Contact Form 7 a la API de LexHoy para procesamiento de leads.

## Características

- ✅ Captura automática de envíos de Contact Form 7
- ✅ Envío a API de LexHoy (apiBackLexHoy)
- ✅ Configuración de URL de API desde el admin
- ✅ Logs de debugging
- ✅ Página de configuración en WordPress

## Instalación

1. Sube la carpeta `cf7-to-lexhoy-api` a `/wp-content/plugins/`
2. Activa el plugin desde el panel de WordPress
3. Ve a **Ajustes > CF7 to LexHoy API** para configurar la URL de la API

## Configuración

### URL de la API

Por defecto: `https://apibacklexhoy.onrender.com/api/leads`

Puedes cambiarla desde **Ajustes > CF7 to LexHoy API**

### Campos del formulario Contact Form 7

El plugin espera los siguientes nombres de campos:

- `your-name` - Nombre del contacto (requerido)
- `your-email` - Email del contacto (requerido)
- `your-phone` - Teléfono (opcional)
- `your-message` - Mensaje/consulta (requerido)
- `acceptance-terms` - Checkbox de aceptación de términos (opcional)

### Ejemplo de formulario Contact Form 7

```
<label> Tu nombre (obligatorio)
    [text* your-name] </label>

<label> Tu correo electrónico (obligatorio)
    [email* your-email] </label>

<label> Teléfono
    [tel your-phone] </label>

<label> Tu mensaje
    [textarea* your-message] </label>

[acceptance acceptance-terms] Acepto los términos y condiciones

[submit "Enviar"]
```

## Funcionamiento

1. Usuario llena formulario Contact Form 7 en lexhoy.com
2. Plugin captura el envío con el hook `wpcf7_mail_sent`
3. Envía los datos a la API de LexHoy vía POST
4. La API procesa el lead con IA y lo guarda en Supabase
5. Se notifica a los despacho_admin en el dashboard

## Debugging

Los logs se guardan en el error log de WordPress. Puedes verlos activando `WP_DEBUG` en `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Los logs estarán en `/wp-content/debug.log`

## Soporte

Para soporte técnico, contacta con el equipo de desarrollo de LexHoy.
