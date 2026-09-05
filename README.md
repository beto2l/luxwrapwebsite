# 🚗 LuxWrap Studio Website

**Premium Vehicle Wrapping** — Frankfort, KY

Sitio web estático para LuxWrap Studio, el mejor servicio de wrapping de vehículos en Frankfort, Kentucky.

## 📁 Estructura del Proyecto

```
luxwrapstudio/
├── index.html              # Página principal (one-page)
├── deploy.php              # Script de auto-actualización
├── assets/
│   ├── css/                # Estilos
│   ├── js/                 # JavaScript
│   │   └── main.js         # Script principal
│   ├── images/             # Imágenes generales (logo, etc.)
│   └── portfolio/          # Fotos del portafolio
│       └── portfolio-*.jpg
├── admin/                  # Panel de administración
│   └── css/                # Estilos del admin
├── scripts/                # Scripts PHP del servidor
├── data/                   # Datos dinámicos (portfolio.json)
├── uploads/                # Imágenes subidas por el admin
└── .htaccess               # Configuración Apache
```

## 🚀 Publicación desde WordPress

El sitio público ahora se despliega con el módulo OPIN X LuxWrap Studio. Después de cambiar HTML o assets públicos, genera y publica un manifiesto nuevo:

```bash
php scripts/build-lw-release.php 1.0.1
```

Luego entra al WordPress de este dominio, abre `OPIN X > LuxWrap Studio` y selecciona **Buscar e instalar actualización**. El módulo instala únicamente los archivos públicos listados con su checksum SHA-256 en `lw-release.json`; excluye PHP, credenciales, paneles administrativos y scripts de despliegue.

El antiguo `deploy.php` se conserva en el repositorio solamente como referencia histórica y no forma parte de LW Release.

## 📞 Contacto

- **Teléfono:** (859) 636-7294
- **Email:** luxwrapstudioky@gmail.com
- **TikTok:** [@luxwrap.studio](https://tiktok.com/@luxwrap.studio)
- **Instagram:** [@luxwrap_studio](https://instagram.com/luxwrap_studio)

## 📝 Licencia

© 2026 LuxWrap Studio. Todos los derechos reservados.
