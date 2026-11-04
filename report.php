<?php

declare(strict_types=1);

// The report now lives on the Dashboard; keep old links working.
header('Location: dashboard.php', true, 301);
exit;
