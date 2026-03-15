<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth.php';

logoutUser();
redirect('../../index.php');
