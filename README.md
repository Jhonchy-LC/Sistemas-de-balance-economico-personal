# Sistema de Ahorros Personal

Aplicacion web en PHP para control personal de ahorros y gastos. Incluye autenticacion, registro de movimientos, edicion, eliminacion e indicadores por periodos.

## Funcionalidades

- Registro e inicio de sesion por usuario
- Registro de movimientos de tipo ahorro o gasto
- Resumen global: ahorros, gastos y saldo
- Resumen por periodos: dia, semana y mes
- Vista anual con detalle mensual
- Historial mensual con opcion de editar y eliminar
- Proteccion CSRF en formularios

## Requisitos

- PHP 8.0 o superior
- Extensiones PDO habilitadas
- MySQL o SQLite

## Configuracion

1. Copia y ajusta configuracion desde [config.php.example](config.php.example) hacia [config.php](config.php).
2. Define el driver con `DB_DRIVER`:
	- `mysql` para hosting (recomendado en Hostinger)
	- `sqlite` para pruebas locales rapidas

## Ejecucion local

Desde la carpeta del proyecto:

```bash
php -S localhost:8000
```

Abrir en navegador:

```text
http://localhost:8000
```

## Despliegue en Hostinger

1. Crear base de datos MySQL en hPanel.
2. Configurar credenciales en [config.php](config.php).
3. Subir archivos a `public_html`.
4. Verificar que PHP y extensiones PDO esten habilitadas.

## Seguridad recomendada

- No subir credenciales reales al repositorio.
- Mantener [config.php](config.php) fuera de control de versiones.
- Usar contrasenas fuertes para base de datos y usuarios.
- Activar HTTPS en dominio de produccion.
