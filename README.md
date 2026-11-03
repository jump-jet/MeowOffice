# MeowOffice

A cozy PHP + MySQL back office with a cute animal interface.

Path on this machine: `R:\laragon\www\MeowOffice`

## Run with Laragon

1. Start **Laragon** (Apache + MySQL).
2. Open **http://meowoffice.test/install.php**
   - If that host is not live yet, click **Menu → Apache → Reload** (or restart Laragon) so it picks up the folder.
   - Fallback URL: **http://localhost/MeowOffice/install.php**
3. Installer MySQL defaults for Laragon:
   - Host: `127.0.0.1`
   - Database: `meowoffice`
   - User: `root`
   - Password: *(usually blank)*
4. Create the first admin, then use Login, Report, Users, History, and Password.

## Features

- Login
- Register
- Change password
- View users
- View history
- View report

Passwords use `password_hash`. Forms use CSRF tokens and PDO prepared statements.

## Pages

| Page | File |
| --- | --- |
| Login | `login.php` |
| Register | `register.php` |
| Report | `report.php` |
| Users | `users.php` |
| History | `history.php` |
| Change password | `change-password.php` |
