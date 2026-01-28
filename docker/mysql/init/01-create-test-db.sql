-- Create test database and grant privileges to user app (runs on first container start)
CREATE DATABASE IF NOT EXISTS app_test;
GRANT ALL PRIVILEGES ON app_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
