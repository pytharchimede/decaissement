<?php

session_start();

// Redirection vers la page d'accueil ou tableau de bord
header('Location: admin.php'); // Redirige vers l'interface administrateur par défaut
exit;