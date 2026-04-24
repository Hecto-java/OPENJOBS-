<?php
echo "cURL habilitado: " . (function_exists('curl_init') ? "SÍ ✅" : "NO ❌") . "<br>";
echo "PHP versión: " . phpversion() . "<br>";
echo "DeepSeek API Key configurada: " . (defined('DEEPSEEK_API_KEY') && DEEPSEEK_API_KEY !== 'sk-bdd5a49efe9d4b66900162f22135d085' ? "SÍ ✅" : "NO ❌");
?>