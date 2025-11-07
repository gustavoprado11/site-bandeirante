<?php
@session_start();
date_default_timezone_set('America/Sao_Paulo');

// E-mails padrão (podem ser sobrescritos por forms/config.php)
$EMAIL_TO   = 'compliance@siderurgicabandeirante.ind.br';
$EMAIL_FROM = 'nao-responder@siderurgicabandeirante.ind.br';

// Carrega config opcional (você vai criar o config.php a partir do .sample)
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
  include $config_path;
  if (defined('CANAL_EMAIL_TO'))   $EMAIL_TO   = CANAL_EMAIL_TO;
  if (defined('CANAL_EMAIL_FROM')) $EMAIL_FROM = CANAL_EMAIL_FROM;
}

// Anti-spam e rate limit (120s)
if (!empty($_SESSION['last_submit']) && time() - $_SESSION['last_submit'] < 120) {
  header('Location: ../denuncias-erro.html'); exit;
}
if (!empty($_POST['website'])) { // honeypot
  header('Location: ../denuncias-erro.html'); exit;
}

function field($k){ return trim($_POST[$k] ?? ''); }

$anonimo   = isset($_POST['anonimo']) && $_POST['anonimo']==='1';
$nome      = $anonimo ? '' : field('nome');
$email     = $anonimo ? '' : field('email');
$telefone  = $anonimo ? '' : field('telefone');
$vinculo   = field('vinculo');
$categoria = field('categoria');
$local     = field('local');
$descricao = field('descricao');
$aceite    = isset($_POST['aceite']);

// Validação mínima
if (!$aceite || empty($vinculo) || empty($categoria) || empty($descricao)) {
  header('Location: ../denuncias-erro.html'); exit;
}

// Protocolo
function ticket(){
  $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $o='';
  for($i=0;$i<8;$i++){ $o.=$a[random_int(0,strlen($a)-1)]; }
  return $o;
}
$t = ticket();

// Monta e-mail
$subject = "[Canal de Denúncias] Protocolo $t";
$lines = [];
$lines[] = "Protocolo: $t";
$lines[] = "Data/Hora: ".date('Y-m-d H:i:s');
$lines[] = "Anônimo: ".($anonimo?'Sim':'Não');
if(!$anonimo){
  if($nome)     $lines[] = "Nome: $nome";
  if($email)    $lines[] = "E-mail: $email";
  if($telefone) $lines[] = "Telefone: $telefone";
}
$lines[] = "Vínculo: $vinculo";
$lines[] = "Categoria: $categoria";
if($local) $lines[] = "Local/Unidade: $local";
$lines[] = str_repeat('-', 50);
$lines[] = "Descrição:";
$lines[] = $descricao;
$message = implode("\n", $lines);

// Headers
$headers = [];
$headers[] = "From: $EMAIL_FROM";
if(!$anonimo && $email) $headers[] = "Reply-To: $email";
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";

// Envia (na sua hospedagem real deve funcionar; em localhost pode falhar)
$sent = @mail($EMAIL_TO, $subject, $message, implode("\r\n", $headers));
$_SESSION['last_submit'] = time();

// Em localhost, considere sucesso para facilitar o teste do fluxo
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!$sent && strpos($host, 'localhost') !== false) {
  header('Location: ../denuncias-sucesso.html?ticket='.urlencode($t)); exit;
}

// Redireciona
if ($sent) {
  header('Location: ../denuncias-sucesso.html?ticket='.urlencode($t));
} else {
  header('Location: ../denuncias-erro.html');
}
exit;
