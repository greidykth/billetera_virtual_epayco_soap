## Servicio Soap Billetera Virtual ePayco 

Servicio soap para una aplicación que simula una billetera virtual.

# Seguir los siguientes pasos para correr la aplicación:

1) Clonar proyecto
2) Correr el comando composer install
3) Crear una base de datos, nombre sugerido billetera_virtual_epayco
4) Crear un virtual host para la aplicación, nombre sugerido http://billetera-virtual-soap.test
5) Tomar el archivo .env.example como base para configurar archivo .env
6) Configurar las variables DB_DATABASE, DB_USERNAME, DB_PASSWORD
7) Abrir en el navegador la url http://billetera-virtual-soap.test para verificar el funcionamiento de la aplicación
8) Obtener api key como lo sugiere el navegador
9) Correr migraciones con el comando php artisan migrate:fresh
10) Verificar funcionamiento de endpoints con la collección de postman enviada

