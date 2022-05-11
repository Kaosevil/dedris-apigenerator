<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (empty($_POST)) {
?>
<html>
<head>
<title>Dedris Api Generator v1.00</title>
<form action="index.php" method="POST">

<label>DB HOST</label>
<input name="db_host">
<br>
<label>DB NAME</label>
<input name="db_name">
<br>
<label>DB USER</label>
<input name="db_user">
<br>
<label>DB PASSWORD</label>
<input name="db_password">
<br>
<button>Generate API</button>
</form>
<?php
} else {
// ------------------------- Inizio Query --------------------------------------------
$db_host = $_POST["db_host"];			    // MySQL Server
$db_name = $_POST["db_name"];			    // MySQL Database
$db_user = $_POST["db_user"];	            // MySQL Username
$db_password = $_POST["db_password"];		// MySQL password
// ------------------------- Fine Query --------------------------------------------<?php
// ------------------------------------------------------------ Connessione al Database 
$con = mysqli_connect( $db_host , $db_user , $db_password, $db_name);
if (!$db_name)  {  die('non riesco a collegarmi al Server del database ' . mysqli_error());
   } mysqli_select_db($con,$db_name); mysqli_query($con,"SET NAMES utf8");
//------------------------------------------------------------- Connessione Effettuata


$sql_query = "SHOW TABLES";
$result = mysqli_query($con, $sql_query);
if (!$result) { echo 'Invalid query: ' . mysqli_error($con); }
$array_tabelle = array();
while( $row=mysqli_fetch_assoc( $result ) ){ $array_tabelle[] = $row; }
echo "<pre class=\"language-php\"><code class=\"language-php\">";

echo '&lt;?php
header(\'Access-Control-Allow-Origin: *\');
header(\'Content-Type: application/json\');


function generateRandomString($length = 40) {
    $characters = \'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ\';
    $charactersLength = strlen($characters);
    $randomString = \'\';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Generate Random Password
function generate_random_password() {
    $chars = \'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz\';
    $count = strlen($chars);
    $bytes = random_bytes(12);
    $password = \'\';
    foreach (str_split($bytes) as $byte) {
        $password .= $chars[ord($byte) % $count];
    }
    return $password;
}

// login
if ($_GET["opt"] == "login") { 

    $username = \'\';
	$password = \'\';

	if (isset($_POST[\'email\'])) { $username = $_POST[\'email\'];}
	if (isset($_POST[\'password\'])) { $password = $_POST[\'password\'];}

	// Controllo le credenziali ricevute, verifico che siano abbinate ad un account attivo
	$sql_query = "SELECT COUNT(`id_user`) as conteggio  FROM `db_user` WHERE `record_status` = \'active\' AND `user_email` = \'".mysqli_real_escape_string($con,$username)."\' ";
	$exec = mysqli_query ($con, $sql_query);
	$result = mysqli_fetch_assoc($exec);
	$check = $result["conteggio"];

	// Controllo l\'esistenza
	if($check == 1){ // Se Esiste
		
		$sql_query = "SELECT * FROM `db_user` WHERE `record_status` = \'active\' AND `user_email` = \'".mysqli_real_escape_string($con,$username)."\' ";
		$exec = mysqli_query ($con, $sql_query);
		$result = mysqli_fetch_assoc($exec);
		$password_in_database = $result["user_password"];
		
		// Vefico se la password inserita è uguale a quella nel database
		if (password_verify($password, $password_in_database)) { 
			$id_user = $result["id_user"];
			$customer_name = $result["user_name"];
			$customer_surname = $result["user_surname"];
			$customer_companyname = $result["user_companyname"];
			$customer_account_type = $result["account_type"];
			
			$exp = time()+28800; // Scadenza Token 8 ore 
			
	
			// Genero il token JWT	
			$payloadArray = array();
			$payloadArray[\'sub\'] = $id_user;
			$payloadArray[\'user_name\'] = $customer_name;
			$payloadArray[\'user_surname\'] = $customer_surname;
			$payloadArray[\'company_name\'] = $customer_companyname;
			$payloadArray[\'account_type\'] = $customer_account_type;

			if (isset($nbf)) {$payloadArray[\'nbf\'] = $nbf;}
			if (isset($exp)) {$payloadArray[\'exp\'] = $exp;}

			$token = JWT::encode($payloadArray, $serverKey);
						
			$data_array[] = array("login" => array("token"=>$token, "exp"=>$exp, "success" => "true")); 
			echo json_encode($data_array);
		} else {// Se password diversa
		$data_array[] = array("login" => array("feedback"=>"Password errata", "success" => "false")); 
		echo json_encode($data_array);
		}
  } else { // Se email non esiste
		$data_array[] = array("login" => array("feedback"=>"Account inesistente", "success" => "false"));
		echo json_encode($data_array);
  }
}

// lost_password
if ($_GET["opt"] == "lost_password") {
	
	$go = "yes";
	
	if (empty($_POST["user_email"])) { $go = "no"; $feedback = "inputneed"; }

	if ($go == "yes") {
		// Verifico che l\'account con questa email esista
		$sql_query = "SELECT COUNT(`id_user`) as conteggio  FROM `db_user` WHERE `record_status` = \'active\' AND `user_email` = \'".mysqli_real_escape_string($con,$_POST["user_email"])."\' ";
		$exec = mysqli_query ($con, $sql_query);
		$result = mysqli_fetch_assoc($exec);
		$check = $result["conteggio"];

		// Controllo l\'esistenza
		if($check == 1){ // Se Esiste		
		// Se l\'email esiste invio l\'email
		$lostpassword_token = generateRandomString();
		
		//$lostpassword_token
		$sql_query = "UPDATE  `db_user` SET `lostpassword_token` = \'".mysqli_real_escape_string($con,$lostpassword_token)."\' WHERE `record_status` = \'active\' AND `user_email` = \'".mysqli_real_escape_string($con,$_POST["user_email"])."\' ";
		$exec = mysqli_query ($con, $sql_query);
			
		// Invio l\'email con il link
		$from_mail = "server@server.com";
		$from_name = "Nome";
		$to_email_recipient = $_POST["user_email"]; //indirizzo@dominio.it 
		$email_subject = "Procedura recupero password";
			
		
			
		// Build the email content.
		$email_body = \'
		Buongiorno,&lt;br>
		&lt;br>
		Qualcuno ha richiesto la generazione di una nuova password, se sei stato tu clicca sul seguente link:<br>
		Link: &lt;a href="http://nomesito.com/passwordlostverify?token=\'.$lostpassword_token.\'">http://nomesito.com/passwordlostverify?token=\'.$lostpassword_token.\'&lt;/a>
		&lt;br>
		&lt;br>
		Se non sei stato tu puoi ignorare questa email
		\';
		
		$mail = new PHPMailer\PHPMailer\PHPMailer(true);
		try {
			$mail->isSMTP();                                							    //Send using SMTP
			$mail->Host       = $smtp_host;                								    //Set the SMTP server to send through
			$mail->SMTPAuth   = true;                    								    //Enable SMTP authentication
			$mail->Username   = $smtp_username;								                //SMTP username
			$mail->Password   = $smtp_password;								                //SMTP password
			$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
			$mail->Port       = 465;                          								//TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
			$mail->setFrom($from_mail, $from_name);
			$mail->addAddress($to_email_recipient);             							//Name is optional
			$mail->isHTML(true);                               								//Set email format to HTML
			$mail->Subject = $email_subject;
			$mail->Body    = $email_body;
			$mail->AltBody = \'This is HTML Email\';
			$mail->send();
		   // echo \'Email Inviata\';
		} catch (PHPMailer\PHPMailer\Exception $e) {
		   //echo "Errore Invio Email: {$mail->ErrorInfo}";
		}		
			
		
		}
	}
	

	if ($go == "no") {
		if ($feedback == "inputneed") {
			$data_array[] = array("lost_password" => array("feedback"=>"Errore uno o più campi necessari mancanti", "success" => "false"));
		}		
	}	
	if ($go == "yes") {
		$data_array[] = array("lost_password" => array("feedback"=>"Se l\'account esiste abbiamo inviato un\'email", "success" => "true")); 	
	}	
// Feedback
echo json_encode($data_array);	
}	

if ($_GET["opt"] == "lost_password_verify") {

	$go = "yes";
	
	if (empty($_POST["token"])) { $go = "no"; $feedback = "inputneed"; }
	if (empty($_POST["action"])) { $go = "no"; $feedback = "inputneed"; }
	if ($_POST["action"] != \'verify_token\') { $go = "no"; $feedback = "inputneed"; }

	if ($go == "yes") {
		// Verifico che l\'account con questa email esista
		$sql_query = "SELECT COUNT(`id_user`) as conteggio  FROM `db_user` WHERE `record_status` = \'active\' AND `lostpassword_token` = \'".mysqli_real_escape_string($con,$_POST["token"])."\' ";
		$exec = mysqli_query ($con, $sql_query);
		$result = mysqli_fetch_assoc($exec);
		$check = $result["conteggio"];	
		// Controllo l\'esistenza
		if($check == 1){ // Se Esiste		
		// Se l\'email esiste invio l\'email
	 
		$password_clean = generate_random_password();	// Genero la password automaticamente
		$password_crypted = password_hash($password_clean, PASSWORD_BCRYPT); // Crypto la password
	
		$sql_query = "UPDATE  `db_user` SET `user_password` = \'".mysqli_real_escape_string($con,$password_crypted)."\' WHERE `record_status` = \'active\' AND `lostpassword_token` = \'".mysqli_real_escape_string($con,$_POST["token"])."\' ";
		$exec = mysqli_query($con, $sql_query);
			
		$sql_query = "SELECT * FROM `db_user` WHERE `record_status` = \'active\' AND `lostpassword_token` = \'".mysqli_real_escape_string($con,$_POST["token"])."\' ";
		$exec = mysqli_query ($con, $sql_query);
		$result = mysqli_fetch_assoc($exec);			
			
		// Invio l\'email con la nuova password
		$from_mail = "server@lascomail.com";
		$from_name = "StudioEuropa";
		$to_email_recipient = $result["user_email"]; //indirizzo@dominio.it 
		$email_subject = "Procedura recupero password completata";
			
		
		$sql_query = "UPDATE  `db_user` SET `lostpassword_token` = \'\' WHERE `record_status` = \'active\' AND `lostpassword_token` = \'".mysqli_real_escape_string($con,$_POST["token"])."\' ";
		$exec = mysqli_query($con, $sql_query);			
			
		// Build the email content.
		$email_body = \'
		<br>
		Buongiorno,<br>
		<br>
		Ecco la tua nuova password:
		<br>
		<strong>\'.$password_clean.\'</strong>
		
		<br>
		<br>
		
		\';
		$data_array[] = array("lost_password_verify" => array("feedback"=>"Token valido nuova password inviata tramite email", "success" => "true")); 
			
		$mail = new PHPMailer\PHPMailer\PHPMailer(true);
		try {
			$mail->isSMTP();                                  		           	 //Send using SMTP
			$mail->Host       = $smtp_host;                  				  	 //Set the SMTP server to send through
			$mail->SMTPAuth   = true;                                 		   	 //Enable SMTP authentication
			$mail->Username   = $smtp_username;                    				 //SMTP username
			$mail->Password   = $smtp_password;                               	 //SMTP password
			$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; //Enable implicit TLS encryption
			$mail->Port       = 465;                                    		 //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
			$mail->setFrom($from_mail, $from_name);
			$mail->addAddress($to_email_recipient);               				 //Name is optional
			$mail->isHTML(true);                                  				 //Set email format to HTML
			$mail->Subject = $email_subject;
			$mail->Body    = $email_body;
			$mail->AltBody = "This is HTML Email";
			$mail->send();
		   // echo \'Email Inviata\';			
		} catch (PHPMailer\PHPMailer\Exception $e) {
		   //echo "Errore Invio Email: {$mail->ErrorInfo}";
		}		
		
	} else {
			$data_array[] = array("lost_password_verify" => array("feedback"=>"Token non valido", "success" => "false")); 
		}
	}
	
	if ($go == "no") {
		if ($feedback == "inputneed") {
			$data_array[] = array("lost_password_verify" => array("feedback"=>"Errore uno o più campi necessari mancanti", "success" => "false"));
		}		
	}	
	
	
// Feedback
echo json_encode($data_array);		

}


// me
if ($_GET["opt"] == "me") {

	// Verifico se ha il token di autenticazione
	foreach (getallheaders() as $name => $value) {
		if ($name == "Authorization") {
			$bearer_token = $value;
		}
	}
	$bearer_token = str_replace("Bearer ","", $bearer_token);
	
	if (!empty($bearer_token)) {
		// Verifico se il token è valido
		try {
			$payload = JWT::decode($bearer_token, $serverKey, array(\'HS256\'));

			$sql_query = "SELECT * FROM `db_user` WHERE `record_status` = \'active\' AND `id_user` = \'".mysqli_real_escape_string($con, $payload->sub)."\'";
			$exec = mysqli_query ($con, $sql_query);
			$user_details = mysqli_fetch_assoc($exec);			
					
			
			//print_r($payload);
			$payload_array = array(
				\'sub\' => $payload->sub,
				\'user_name\' => $user_details["user_name"],
				\'user_surname\' => $user_details["user_surname"],
				\'company_name\' => $user_details["user_companyname"],
				\'account_type\' => $user_details["account_type"]
			);

			if (isset($payload->exp)) {
				$payload_array[\'exp\'] = date(DateTime::ISO8601, $payload->exp);;
			}

			$data_array[] = array("me" => array("payload"=>$payload_array, "success"=>"true")); // in caso di esito positivo
			echo json_encode($data_array);	
			//print_r($payload_array);
		}
		catch(Exception $e) {
			$data_array[] = array("me" => array("error"=>$e->getMessage(), "success"=>"false")); // in caso di esito positivo
			echo json_encode($data_array);			
		}
	} else {
		$data_array[] = array("me" => array("error"=>"Bearer Token Assente", "success"=>"false")); // in caso di esito positivo
		echo json_encode($data_array);			
	}

}


// register
if ($_GET["opt"] == "register") {
	
$go = "yes";
	
	// Controllo che i campi siano tutti presenti
	if (empty($_POST["user_email"])) { $go = "no"; $feedback = "inputneed"; }
	if (empty($_POST["user_name"])) { $go = "no"; $feedback = "inputneed"; }
	if (empty($_POST["user_surname"])) { $go = "no"; $feedback = "inputneed"; }
	// Fine validazione campi
	
	// Controllo se ha già un account
	$sql_query = "SELECT COUNT(*) AS conteggio FROM db_user WHERE user_email = \'".mysqli_real_escape_string($con, $_POST["user_email"])."\' ";
	$exec = mysqli_query ($con, $sql_query);
	$result = mysqli_fetch_assoc($exec);

	if ($result["conteggio"] != 0) { $go = "no"; $feedback = "duplicateaccount"; }	
	
	if ($go == "yes") {

		// Password 
		$password_clean = generate_random_password();	// Genero la password automaticamente
		$password_crypted = password_hash($password_clean, PASSWORD_BCRYPT); // Crypto la password

		// Data Creazione Record
		$creation_date = date("Y-m-d h:m:s");

		// Query di Insert
		$sql_query = "INSERT INTO `db_user` (
		`user_email`,
		`user_password`,
		`user_name`,
		`user_surname`,
		`creation_date`
		) VALUES ( 
		\'".mysqli_real_escape_string($con,$_POST["user_email"])."\',
		\'".$password_crypted."\',
		\'".mysqli_real_escape_string($con,$_POST["user_name"])."\',
		\'".mysqli_real_escape_string($con,$_POST["user_surname"])."\',
		\'".mysqli_real_escape_string($con,$creation_date)."\'
		)";	
		$result = mysqli_query($con, $sql_query);  	
		if (!$result) { 
			$data_array[] = array("register" => array("feedback"=>"Invalid query:". mysqli_error($con), "success" => "false")); // In caso di errore query
		} else {
			$data_array[] = array("register" => array("success"=>"true")); // in caso di esito positivo


			// Invio l\'email di benvenuto
			$from_mail = "server@server.com";
			$from_name = "NomeMittente";
			$to_email_recipient = $_POST["user_email"]; //indirizzo@dominio.it 
			$email_subject = "Registrazione avvenuta con successo";

			// Build the email content.
			$email_body = \'
			<br>
			Buongiorno,<br>
			<br>
			Registrazione avvenuta con successo<br><br>
			Username: \'.$_POST["user_email"].\'<br>
			Password: \'.$password_clean.\' (Potrai cambiarla una volta effettuato l accesso)<br>
			<br>

			\';

			$mail = new PHPMailer\PHPMailer\PHPMailer(true);
			try {
				$mail->isSMTP();                                    			        //Send using SMTP
				$mail->Host       = $smtp_host;                    						//Set the SMTP server to send through
				$mail->SMTPAuth   = true;                             				    //Enable SMTP authentication
				$mail->Username   = $smtp_username;					                    //SMTP username
				$mail->Password   = $smtp_password;		                                //SMTP password
				$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;    //Enable implicit TLS encryption
				$mail->Port       = 465;			                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
				$mail->setFrom($from_mail, $from_name);
				$mail->addAddress($to_email_recipient);           					    //Name is optional
				$mail->isHTML(true);                              					    //Set email format to HTML
				$mail->Subject = $email_subject;
				$mail->Body    = $email_body;
				$mail->AltBody = "This is HTML Email";
				$mail->send();
			   // echo "Email Inviata";
			} catch (PHPMailer\PHPMailer\Exception $e) {
			   //echo "Errore Invio Email: {$mail->ErrorInfo}";
			}
		}
	} else {
		
		if ($go == "no") {
			if ($feedback == "inputneed") {
				$data_array[] = array("register" => array("feedback"=>"Errore uno o più campi necessari mancanti", "success" => "false"));
			}
			if ($feedback == "duplicateaccount") {
				$data_array[] = array("register" => array("feedback"=>"Errore email già registrata", "success" => "false"));
			}		
		}
	}
		
// Feedback
echo json_encode($data_array);	
}



// token_refresh
if ($_GET["opt"] == "token_refresh") {

	// Verifico se ha il token di autenticazione
	foreach (getallheaders() as $name => $value) {
		if ($name == "Authorization") {
			$bearer_token = $value;
		}
	}
	$bearer_token = str_replace("Bearer ","", $bearer_token);
	
	if (!empty($bearer_token)) {
		// Verifico se il token è valido
		try {
			$payload = JWT::decode($bearer_token, $serverKey, array(\'HS256\'));
					
			$payload_array = array(
				\'sub\' => $payload->sub,
				\'user_name\' => $payload->user_name,
				\'user_surname\' => $payload->user_surname,
				\'company_name\' => $payload->company_name,
				\'account_type\' => $payload->account_type
			);

			$exp = time()+28800; // Scadenza Token 8 ore 
			
			if (isset($exp)) {
				$payload_array[\'exp\'] = $exp;//date(DateTime::ISO8601, $exp);;
			}
			
			$token = JWT::encode($payload_array, $serverKey);
						
			$data_array[] = array("token_refresh" => array("new_token"=>$token, "success" => "true")); 
			echo json_encode($data_array);			
			
			//print_r($payload_array);
		}
		catch(Exception $e) {
			$data_array[] = array("me" => array("error"=>$e->getMessage(), "success"=>"false")); // in caso di esito positivo
			echo json_encode($data_array);			
		}
	} else {
		$data_array[] = array("me" => array("error"=>"Bearer Token Assente", "success"=>"false")); // in caso di esito positivo
		echo json_encode($data_array);			
	}

}

';
foreach ($array_tabelle as $tabella) {
	//echo '<li>'; echo '<a href="?table='.$tabella["Tables_in_".$db_name].'">'.$tabella["Tables_in_".$db_name].'</a>'; echo "</li>";


$tabella_selezionata = $tabella["Tables_in_".$db_name];
	
// Mi estrapolo la chiave primaria
$sql_query = 'SHOW COLUMNS FROM '.$tabella["Tables_in_".$db_name].'';
$result = mysqli_query($con, $sql_query); 
if (!$result) { echo 'Invalid query: ' . mysqli_error($con); }
$array_colonne = array();
while( $row=mysqli_fetch_assoc( $result ) ){ $array_colonne[] = $row; }
		
echo '
// '.$tabella_selezionata.'_addnew
if ($_GET["opt"] == "'.$tabella_selezionata.'_addnew") {
	// Verifico se ha il token di autenticazione
	foreach (getallheaders() as $name => $value) {
		if ($name == "Authorization") {
			$bearer_token = $value;
		}
	}
	$bearer_token = str_replace("Bearer ","", $bearer_token);

	
	if (!empty($bearer_token)) {
		// Verifico se il token è valido
		try {
			$payload = JWT::decode($bearer_token, $serverKey, array(\'HS256\'));
			
			// Verifico se ci sono tutti i campi
			$go = "yes";
			
			// Controllo che i campi siano tutti presenti
			';

foreach ($array_colonne as $colonne) { if ($colonne["Key"] != "PRI") { echo 'if (empty($_POST["'.$colonne["Field"].'"])) { $go = "no"; $feedback = "inputneed"; $inputname = "'.$colonne["Field"].'"; }
			'; } }	

echo '
			if ($go == "no") {
				if ($feedback == "inputneed") {
					$data_array[] = array("'.$tabella_selezionata.'_addnew" => array("feedback"=>"Campo necessario ".$inputname." assente", "success" => "false"));
					echo json_encode($data_array);	
					exit();			
				}	
			}
';
	
echo '
		$creation_date = date("Y-m-d H:i:s");
		// Inserisco il progetto in database
		$sql_query = "INSERT INTO `'.$tabella_selezionata.'` (
';
//========================================================================================
$conteggio = count($array_colonne);
$element_number = 0;
foreach ($array_colonne as $colonne) {

if($element_number != $conteggio-1) {
	if ($colonne["Key"] != "PRI") { echo '		 `'.$colonne["Field"].'`,
'; } 
} else { // Se ultima riga
	if ($colonne["Key"] != "PRI") { echo '		 `'.$colonne["Field"].'`
'; }
}
	$element_number++;
}
//========================================================================================	
echo '		 ) VALUES ( 
';

//========================================================================================
$conteggio = count($array_colonne);
$element_number = 0;
foreach ($array_colonne as $colonne) {

if($element_number != $conteggio-1) {
	if ($colonne["Key"] != "PRI") { echo '		 \'".mysqli_real_escape_string($con,$_POST["'.$colonne["Field"].'"])."\',
'; } 
} else { // Se ultima riga
	if ($colonne["Key"] != "PRI") { echo '		 \'".mysqli_real_escape_string($con,$_POST["'.$colonne["Field"].'"])."\'
'; }
}
	$element_number++;
}
//========================================================================================	
	//	'".mysqli_real_escape_string($con,$_POST["project_name"])."',
echo '		)";
		$result = mysqli_query($con, $sql_query);  	
		if (!$result) { echo \'Invalid query: \' . mysqli_error($con); }	

		$data_array[] = array("'.$tabella_selezionata.'_addnew" => array("message"=>"record salvato con successo", "success"=>"true")); // in caso di esito negativo
		echo json_encode($data_array);
			
		}
		catch(Exception $e) {
			$data_array[] = array("'.$tabella_selezionata.'_addnew" => array("error"=>$e->getMessage(), "success"=>"false")); // in caso di esito negativo
			echo json_encode($data_array);			
		}
	} else {
		$data_array[] = array("'.$tabella_selezionata.'_addnew" => array("error"=>"Bearer Token Assente", "success"=>"false")); // in caso di esito negativo
		echo json_encode($data_array);			
	}
}
';
	

echo '
// '.$tabella_selezionata.'_details
if ($_GET["opt"] == "'.$tabella_selezionata.'_details") {

	// Verifico se ha il token di autenticazione
	foreach (getallheaders() as $name => $value) {
		if ($name == "Authorization") {
			$bearer_token = $value;
		}
	}
	$bearer_token = str_replace("Bearer ","", $bearer_token);
	
	if (empty($_POST["'; foreach ($array_colonne as $colonne) { if ($colonne["Key"] == "PRI") { echo $colonne["Field"]; } } echo '"])) { 
		$data_array[] = array("'.$tabella_selezionata.'_details" => array("error"=>"Campo necessario \''; foreach ($array_colonne as $colonne) { if ($colonne["Key"] == "PRI") { echo $colonne["Field"]; } }  echo '\' assente", "success"=>"false")); // in caso di esito negativo
		echo json_encode($data_array);	
		exit();
	}
	
	if (!empty($bearer_token)) {
		// Verifico se il token è valido
		try {
			$payload = JWT::decode($bearer_token, $serverKey, array(\'HS256\'));

			$sql_query = "SELECT * FROM `'.$tabella_selezionata.'` WHERE `record_status` = \'active\' AND `id_project` = \'".mysqli_real_escape_string($con, $_POST["id_project"])."\' ";
			$exec = mysqli_query ($con, $sql_query);
			$'.$tabella_selezionata.'_details = mysqli_fetch_assoc($exec);			
				
			$payload_array = array(
';
$conteggio = count($array_colonne);
$element_number = 0;
foreach ($array_colonne as $colonne) {

if($element_number != $conteggio-1) {
	if ($colonne["Key"] != "PRI") { echo '				\''.$colonne["Field"].'\' => $'.$tabella_selezionata.'_details["'.$colonne["Field"].'"],
'; } 
} else { // Se ultima riga
	if ($colonne["Key"] != "PRI") { echo '		 		\''.$colonne["Field"].'\' => $'.$tabella_selezionata.'_details["'.$colonne["Field"].'"]
'; }
}
	$element_number++;
}

echo '			);



			$data_array[] = array("'.$tabella_selezionata.'_details" => array("payload"=>$payload_array, "success"=>"true")); // in caso di esito positivo
			echo json_encode($data_array);	
			 
		}
		catch(Exception $e) {
			$data_array[] = array("'.$tabella_selezionata.'_details" => array("error"=>$e->getMessage(), "success"=>"false")); // in caso di esito positivo
			echo json_encode($data_array);			
		}
	} else {
		$data_array[] = array("'.$tabella_selezionata.'_details" => array("error"=>"Bearer Token Assente", "success"=>"false")); // in caso di esito positivo
		echo json_encode($data_array);			
	}

}

';


echo '
// '.$tabella_selezionata.'_edit
if ($_GET["opt"] == "'.$tabella_selezionata.'_edit") {

	// Verifico se ha il token di autenticazione
	foreach (getallheaders() as $name => $value) {
		if ($name == "Authorization") {
			$bearer_token = $value;
		}
	}
	$bearer_token = str_replace("Bearer ","", $bearer_token);
	
	
	// Controllo che i campi siano tutti presenti
	$go = "yes";
	
	if (empty($_POST["'; foreach ($array_colonne as $colonne) { if ($colonne["Key"] == "PRI") { echo $colonne["Field"]; } } echo '"])) { 
		$data_array[] = array("'.$tabella_selezionata.'_edit" => array("error"=>"Campo necessario \''; foreach ($array_colonne as $colonne) { if ($colonne["Key"] == "PRI") { echo $colonne["Field"]; } }  echo '\' assente", "success"=>"false")); // in caso di esito negativo
		echo json_encode($data_array);	
		exit();
	}
	
	';
	
foreach ($array_colonne as $colonne) { if ($colonne["Key"] != "PRI") { echo 'if (empty($_POST["'.$colonne["Field"].'"])) { $go = "no"; $feedback = "inputneed"; $inputname = "'.$colonne["Field"].'"; }
	'; } }	
	echo '

	if ($go == "no") {
		if ($feedback == "inputneed") {
			$data_array[] = array("'.$tabella_selezionata.'_edit" => array("feedback"=>"Campo necessario \'".$inputname."\' assente", "success" => "false"));
			echo json_encode($data_array);	
			exit();			
		}	
	}	
	
	if(!empty($bearer_token)) {
		try {
			$payload = JWT::decode($bearer_token, $serverKey, array(\'HS256\'));

			$sql_query = "UPDATE `'.$tabella_selezionata.'` SET
';
	
$conteggio = count($array_colonne);
$element_number = 0;
foreach ($array_colonne as $colonne) {

if($element_number != $conteggio-1) {
	if ($colonne["Key"] != "PRI") { echo '			`'.$colonne["Field"].'` = \'".mysqli_real_escape_string($con, $_POST["'.$colonne["Field"].'"])."\',
'; } 
} else { // Se ultima riga
	if ($colonne["Key"] != "PRI") { echo '			`'.$colonne["Field"].'` = \'".mysqli_real_escape_string($con, $_POST["'.$colonne["Field"].'"])."\'
'; }
}
	$element_number++;
}
	
// esempio -> `id_project` = '".mysqli_real_escape_string($con, $_POST["id_project"])."',
echo '			WHERE `'.$tabella_selezionata.'` =  \'".mysqli_real_escape_string($con, $_POST["';  foreach ($array_colonne as $colonne) { if ($colonne["Key"] == "PRI") { echo $colonne["Field"]; } }  echo '"])."\'";	
			$result = mysqli_query($con, $sql_query);  	
			if (!$result) { $data_array[] = array("'.$tabella_selezionata.'_edit" => array("error" => "Invalid query")); }			
			
			$data_array[] = array("'.$tabella_selezionata.'_edit" => array("success" => "true"));
			echo json_encode($data_array);
		} catch(Exception $e) {
			echo $e;
		}
		
	} else {
		$data_array[] = array("'.$tabella_selezionata.'_edit" => array("error" => "Bearer Token Assente", "success" => "false"));
		echo json_encode($data_array);
	}			
}
';
	

echo '
// '.$tabella_selezionata.'_datatable
if ($_GET["opt"] == "'.$tabella_selezionata.'_datatable") {

	// Verifico se ha il token di autenticazione
	foreach (getallheaders() as $name => $value) {
		if ($name == "Authorization") {
			$bearer_token = $value;
		}
	}
	$bearer_token = str_replace("Bearer ","", $bearer_token);
	
	if (!empty($bearer_token)) {
		// Verifico se il token è valido
		try {
			$payload = JWT::decode($bearer_token, $serverKey, array(\'HS256\'));
		
			$sql_query = "SELECT * FROM `'.$tabella_selezionata.'` WHERE `record_status` = \'active\' ";
			$result = mysqli_query ($con, $sql_query);
			if (!$result) { echo \'Invalid query: \' . mysqli_error($con); }
			$array_'.$tabella_selezionata.' = array();
			while( $row=mysqli_fetch_assoc( $result ) ){ $array_'.$tabella_selezionata.'[] = $row; }

			$stack = array();
			foreach ($array_'.$tabella_selezionata.' as $'.$tabella_selezionata.'_element) {

					array_push($stack, array(
';
$conteggio = count($array_colonne);
$element_number = 0;
foreach ($array_colonne as $colonne) {

if($element_number != $conteggio-1) {
	if ($colonne["Key"] != "PRI") { echo '						"'.$colonne["Field"].'"=>$'.$tabella_selezionata.'_element["'.$colonne["Field"].'"],
'; } 
} else { // Se ultima riga
	if ($colonne["Key"] != "PRI") { echo '						"'.$colonne["Field"].'"=>$'.$tabella_selezionata.'_element["'.$colonne["Field"].'"]
'; }
}
	$element_number++;
}
	//		"creation_date"=>$project_info["creation_date"], 
					echo '					));

			}			

			echo json_encode($stack);	

		}
		catch(Exception $e) {
			$data_array[] = array("'.$tabella_selezionata.'_datatable" => array("error"=>$e->getMessage(), "success"=>"false")); // in caso di esito negativo
			echo json_encode($data_array);			
		}
	} else {
		$data_array[] = array("'.$tabella_selezionata.'_datatable" => array("error"=>"Bearer Token Assente", "success"=>"false")); // in caso di esito negativo
		echo json_encode($data_array);			
	}
}
';	
	
echo "?&gt;
</code></pre>";	
echo "</div>";	
//}

	


}
		
mysqli_close($con);
}
?>