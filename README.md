# SmartBancs App

MVP de una plataforma transaccional financiera con procesamiento de alta concurrencia, integración desacoplada con un core legado, recomendaciones de IA asíncronas, y observabilidad básica. Desarrollado como parte del Reto Técnico NextGen Engineer.

Documento Técnico: https://drive.google.com/file/d/1tcZ6rHDw3Zs-f5X6sBrnByZlyLMYPslr/view?usp=sharing

Video Demostrativo: https://drive.google.com/file/d/10DP34_0Wx6YSzlhDDxQoqdIuDvQ4kT-S/view?usp=sharing

---

## Prerrequisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (con el motor de Docker corriendo)
- PowerShell (incluido en Windows) o cualquier shell equivalente en macOS/Linux
- Puertos libres en el host: `8000` (API), `5432` (PostgreSQL), `6379` (Redis)

No se requiere tener PHP, Composer ni PostgreSQL instalados localmente — todo corre dentro de contenedores.

---

## Instalación y arranque

1. Clonar el repositorio y ubicarse en la raíz del proyecto:
   ```bash
   git clone <url-del-repositorio>
   cd smartbancs-app
   ```

2. Levantar el entorno completo con un solo comando (backend con Laravel + Octane/Swoole, worker de colas, PostgreSQL y Redis):
   ```bash
   docker compose up --build
   ```
   La primera vez tarda varios minutos (compila la extensión Swoole). Al terminar, deberías ver:
   ```
   INFO  Server running....
   Local: http://0.0.0.0:8000
   ```

3. En otra terminal, ejecutar las migraciones y cargar datos de prueba:
   ```bash
   docker compose exec backend php artisan migrate
   docker compose exec backend php artisan db:seed
   ```

4. Verificar que el servicio responde:
   ```bash
   curl http://localhost:8000
   ```
   Debería devolver la página de bienvenida de Laravel.

---

## Obtener los IDs de las cuentas de prueba

El seeder crea dos cuentas con saldo inicial. Para consultar sus UUIDs:

```bash
docker compose exec backend php artisan tinker
```
```php
App\Models\Account::all(['id','numero_cuenta','saldo_cache']);
exit
```

---

## Probar el endpoint transaccional

**Linux/macOS:**
```bash
curl -X POST http://localhost:8000/api/transactions \
  -H "Content-Type: application/json" \
  -d '{"idempotency_key":"prueba-001","cuenta_origen_id":"<UUID_CUENTA>","monto":100}'
```

**Windows (PowerShell):**
```powershell
$body = @{ idempotency_key = "prueba-001"; cuenta_origen_id = "<UUID_CUENTA>"; monto = 100 } | ConvertTo-Json
Invoke-RestMethod -Uri "http://localhost:8000/api/transactions" -Method Post -Body $body -ContentType "application/json"
```

Consultar una transacción existente (con su recomendación de IA asociada):
```bash
curl http://localhost:8000/api/transactions/<ID_DE_LA_TRANSACCION>
```

---

## Observabilidad

- **Métricas Prometheus:** `http://localhost:8000/metrics`
- **Logs estructurados (JSON) de operaciones críticas:**
  ```bash
  docker compose exec backend cat storage/logs/transactions.log
  ```
- **Logs del worker de IA en tiempo real:**
  ```bash
  docker compose logs queue-worker -f
  ```

---

## Ejecutar la demostración del incidente (deadlock forzado)

Requiere dos terminales simultáneas:

```bash
# Terminal A
docker compose exec backend php artisan demo:deadlock 1

# Terminal B (casi inmediatamente después)
docker compose exec backend php artisan demo:deadlock 2
```

Uno de los dos procesos debe terminar con `DEADLOCK DETECTADO` (`SQLSTATE[40P01]`), visible también en `storage/logs/transactions.log`.

---

## Ejecutar el ETL de transformación de datos

```bash
docker compose exec backend php artisan etl:transform-transactions
```

Genera `storage/app/etl/transacciones_procesadas.json` a partir del lote crudo en `storage/app/etl/transacciones_raw.csv`. Acepta rutas personalizadas:

```bash
docker compose exec backend php artisan etl:transform-transactions {ruta_entrada} {ruta_salida}
```

---

## Ejecutar pruebas de carga concurrente (opcional)

Ver la sección 9 de [manual-demo-smartbancs.md](./manual-demo-smartbancs.md) para el script completo de PowerShell que dispara 20 transferencias simultáneas contra la misma cuenta.

---

## Detener el entorno

```bash
docker compose down
```

Para eliminar también los datos persistidos de PostgreSQL:
```bash
docker compose down -v
```

---

## Estructura del repositorio

```
smartbancs-app/
├── backend/                   # Aplicación Laravel (API, modelos, jobs, comandos)
├── docker/php/Dockerfile      # Imagen PHP 8.4 + Swoole + extensiones necesarias
├── docker-compose.yml         # Orquestación de backend, worker, PostgreSQL y Redis
├── README.md
└── declaracion_uso_inteligencia_artificial.md
```
