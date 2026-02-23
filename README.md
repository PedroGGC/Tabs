# PHP Blog with Authentication

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)

A functional blog project built with plain PHP and MySQL, including session-based authentication and full post CRUD with author ownership checks.

## Technologies
- PHP 8.1+
- MySQL
- PDO
- Native PHP Sessions
- `password_hash()` / `password_verify()` with bcrypt
- Minimal HTML/CSS

## Project Structure
```text
blog-php/
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   └── functions.php
├── public/
│   └── css/
│       └── style.css
├── database/
│   └── blog.sql
├── index.php
├── post.php
├── register.php
├── login.php
├── logout.php
├── dashboard.php
├── post-create.php
├── post-edit.php
├── post-delete.php
└── README.md
```

## Installation
1. Clone the repository:
```bash
git clone <repository-url>
```

2. Enter the project folder:
```bash
cd blog-php
```

3. Create the database and tables by importing the SQL script:
- File: `database/blog.sql`
- You can use phpMyAdmin, MySQL Workbench, or terminal:
```bash
mysql -u root -p < database/blog.sql
```

4. Configure your database credentials in `config/database.php`:
- `$host`
- `$port`
- `$dbName`
- `$username`
- `$password`

5. Run the project on a local server (XAMPP/WAMP):
- Place the `blog-php` folder inside `htdocs` (XAMPP) or `www` (WAMP), or configure a virtual host.
- Open in your browser:
```text
http://localhost/blog-php
```

## Usage Flow
1. Open `register.php` to create a user account.
2. Log in via `login.php`.
3. Create, edit, and delete your own posts in `dashboard.php`.
4. View public posts in `index.php` and open details in `post.php?id=X`.

## Security Notes
- Prepared statements (PDO) in all queries.
- Escaped output using `htmlspecialchars`.
- Password hashing with bcrypt.
- Access control for protected pages.
- Author ownership validation for editing/deleting posts.

## Screenshots
<!-- screenshot aqui -->

