<?php
echo "<pre>";
echo "<h1>Gerador de Hash MD5</h1>";

// --- COLOQUE AS SENHAS QUE VOCÊ DESEJA AQUI ----
$senhas = [

    "senha123" => "secretario01",

];

foreach ($senhas as $senha_plana => $usuario) {
    // Gera o hash MD5
    $hash_da_senha = md5($senha_plana);

    echo "------------------------------------------------------<br>";
    echo "Usuário: <strong>" . $usuario . "</strong><br>";
    echo "Senha: <strong>" . $senha_plana . "</strong><br>";
    echo "Hash MD5 (Copie e cole no banco de dados):<br>";
    echo '<textarea rows="1" cols="70" readonly onclick="this.select()">' . $hash_da_senha . '</textarea>';
    echo "<br>------------------------------------------------------<br>";
}

echo "</pre>";
?>