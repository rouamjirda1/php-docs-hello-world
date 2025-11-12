<?php
// index.php

// traitement du formulaire
$name = $email = $message = "";
$nameErr = $emailErr = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // récupération et nettoyage
    $name = trim($_POST['name'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $message = trim($_POST['message'] ?? "");

    // validations simples
    if ($name === "") {
        $nameErr = "Le nom est requis.";
    }

    if ($email === "") {
        $emailErr = "L'email est requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Format d'email invalide.";
    }

    // si tout est ok, on peut traiter (ici on affiche juste un message)
    $success = empty($nameErr) && empty($emailErr);
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Formulaire simple</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background:#f5f7fa; color:#222; }
    .container { max-width:600px; margin:40px auto; background:white; padding:20px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.08); }
    label { display:block; margin-top:12px; font-weight:600; }
    input[type="text"], input[type="email"], textarea {
      width:100%; padding:10px; margin-top:6px; border:1px solid #ccd; border-radius:6px; box-sizing:border-box;
    }
    .error { color:#b00020; font-size:0.9rem; margin-top:6px; }
    .btn { margin-top:14px; padding:10px 16px; border:none; background:#007bff; color:white; border-radius:6px; cursor:pointer; }
    .success { background:#e6ffef; color:#055f2c; padding:10px; border-radius:6px; margin-top:12px; }
    small { color:#666; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Contactez-nous</h2>

    <?php if (!empty($success) && $success): ?>
      <div class="success">
        <strong>Merci, <?= htmlspecialchars($name) ?> !</strong>
        <div>Nous avons reçu votre message :</div>
        <blockquote><?= nl2br(htmlspecialchars($message)) ?></blockquote>
