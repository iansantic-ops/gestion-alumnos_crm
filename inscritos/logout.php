<?php
// ============================================================
// Logout — cierra sesión y redirige al login
// Archivo: logout.php
// NUEVO: manejador de cierre de sesión independiente
// ============================================================
session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit();
