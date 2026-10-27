# compramostuebike.com

Landing de captación de leads para venta de bicicletas eléctricas de segunda mano.

## Despliegue
Cada `push` a `main` se publica en IONOS mediante `.github/workflows/deploy-ionos.yml`.

Secretos necesarios en GitHub (Settings → Secrets and variables → Actions):
- `FTP_SERVER`: servidor FTP de IONOS
- `FTP_USERNAME`: usuario FTP
- `FTP_PASSWORD`: contraseña FTP
- `FTP_TARGET_DIR`: carpeta raíz del dominio (por ejemplo `/htdocs/`)
