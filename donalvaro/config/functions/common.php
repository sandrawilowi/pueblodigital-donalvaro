<?php
/**
 * Common functions
 *
 * @author Wilowi - Sandra Campos
 * @since 05/11/2022
 *
 */

/**
 * Get the IP
 * @return string
 */
function getRealIP() {
	
	$server = filter_input_array(INPUT_SERVER);
	
	if (!empty($server['HTTP_CLIENT_IP'])){
		return $server['HTTP_CLIENT_IP'];
	}

	if (!empty($server['HTTP_X_FORWARDED_FOR'])){
		return $server['HTTP_X_FORWARDED_FOR'];
	}

	return $server['REMOTE_ADDR'];
}

/**
 * Get the user agent
 * @return string
 */
function getUserAgent(){
    
    $server = filter_input_array(INPUT_SERVER);
    
    return $server['HTTP_USER_AGENT'];
    
}

/**
 * Normalice string
 * @param string $str
 * @return sting
 */
function normalize ($str){
    
    $originals = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞ
ßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    $modifs = 'aaaaaaaceeeeiiiidnoooooouuuuy
bsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    $str = utf8_decode($str);
    $str = strtr($str, utf8_decode($originals), $modifs);
    $str = strtolower($str);
    
    return utf8_encode($str);
    
}


/**
 * Check the password security
 * @param String $string
 * @return boolean
 */
function checkSecurityPassowrd($string){
    
    $size = strlen($string);
    
    if($size<6 || $size>15){
	
	return false;
	
    }else{	
	
	/*
	 * at least one lowercase char
	  at least one uppercase char
	  at least one digit
	  at least one special sign of @#-_$%&+=!?
	 */
	if (preg_match('/^(?=.*\d)(?=.*[@#\-_$%^&+=§!\?])(?=.*[a-z])(?=.*[A-Z])[0-9A-Za-z@#\-_$%&+=!\?]{6,15}$/', $string)) {
	    return true;
	} else {
	    return false;
	}
    }
}

/**
 * Calcula hash de un password.
 * @param String $password -> contraseña para calcular
 * @return String ->hash
 */
function securePass($password) {
    
    $password_encrypt = $password;
    $costt = array('cost' => PASSWORD_BCRYPT_DEFAULT_COST);
    $password_encrypt_aux = password_hash($password_encrypt, PASSWORD_BCRYPT, $costt);

    return $password_encrypt_aux;
}

/**
 * Function to send email with the account no-reply@institutodickens.com
 * @param array $addresses
 * @param string $subject
 * @param string $body
 * @param array $attach
 * @return boolean
 */
function sendMail($addresses, $subject, $body, $attach = array(), $replyto='') {

    if (_ENVIRONMENT == 'production') {

	$sendmail = new lmMailer();
        //$sendmail->setReplyTo($replyto);
	$sendmail->setAddress($addresses);

	if (!empty($attach)) {

	    $sendmail->setAttachment($attach);
	}

	//$sendmail->setOpen();
	if ($sendmail->sendStandarMail($subject, $body)) {
	    return true;
	} else {

	    $log = new logsModel();
	    $log->setFolder('mails/');
	    $log->setFile_name('sendMail.txt');
	    $log->setType_msg('ERROR');
	    $log->setMsg('Error with sending emails:  EMAILS: ' . json_encode($addresses) . ' Subject: ' . $subject . ' Message: ' . $body);
	    $log->writeLog();
	    return false;
	}

	//$sendmail->setClose();
    } else {
	return true;
    }
}


function controlErrorFile($error) {

    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
            $this->msg .= "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $this->msg .= "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $this->msg .= "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $this->msg .= "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $this->msg .= "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $this->msg .= "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $this->msg .= "File upload stopped by extension";
            break;

        default:
            $this->msg .= "Unknown upload error";
            break;
    }
}

/**
 * Function to resize images.
 * @param string $rutaOriginal -> url original file.
 * @param string $rutaFinal -> final url ( where save the file)
 * @param integer $anchoNuevo
 * @param integer $altoNuevo
 * @param string $tipo -> image type (jpg,png,gif)
 * @return boolean
 */
function redimensionarImg($rutaOriginal, $rutaFinal, $anchoNuevo, $altoNuevo, $tipo) {

	$imgOrg = '';
	$alto_final = 0;
	$ancho_final = 0;

	// --- Crear imagen original
	if ($tipo == 'image/gif') { # GIF
		if (!$imgOrg = imagecreatefromgif($rutaOriginal)) {
			return false;
		}
	} else if ($tipo == 'image/jpeg') { # JPG 
		if (!$imgOrg = imagecreatefromjpeg($rutaOriginal)) {
			return false;
		}
	} else if ($tipo == 'image/png') { # PNG
		if (!$imgOrg = imagecreatefrompng($rutaOriginal)) {
			return false;
		}
	} else {
		return false;
	}

	// --- Original image size
	if (!list($ancho, $alto) = getimagesize($rutaOriginal)) {
		return false;
	}
        
        if(empty($anchoNuevo)){
            
            $anchoNuevo = $ancho;
        }

	//Ratio
	$x_ratio = $anchoNuevo / $ancho;
	$y_ratio = $altoNuevo / $alto;

	//Width and height
	if (($ancho <= $anchoNuevo) && ($alto <= $altoNuevo)) {
		$ancho_final = $ancho;
		$alto_final = $alto;
	} else if (($x_ratio * $alto) < $altoNuevo) {
		$alto_final = ceil($x_ratio * $alto);
		$ancho_final = $anchoNuevo;
	} else {
		$ancho_final = ceil($y_ratio * $ancho);
		$alto_final = $altoNuevo;
	}

	// Create canvas
	if (!$lienzo = imagecreatetruecolor($ancho_final, $alto_final)) {
		return false;
	}
	
	// Transparency for png
	if ($tipo == 'image/png') { # PNG
		 // integer representation of the color black (rgb: 0,0,0)
        $background = imagecolorallocate($lienzo , 0, 0, 0);
        // removing the black from the placeholder
        imagecolortransparent($lienzo, $background);

        // turning off alpha blending (to ensure alpha channel information
        // is preserved, rather than removed (blending with the rest of the
        // image in the form of black))
        imagealphablending($lienzo, false);

        // turning on alpha channel information saving (to ensure the full range
        // of transparency is preserved)
        imagesavealpha($lienzo, true);
	}

	// Copy original canvas
	if (!imagecopyresampled($lienzo, $imgOrg, 0, 0, 0, 0, $ancho_final, $alto_final, $ancho, $alto)) {
		return false;
	}

	// Delete the original image
	if (!imagedestroy($imgOrg)) {
		return false;
	}

	// Create the image and save it in the images directory
	if ($tipo == 'image/gif') { # GIF
		if (!imagegif($lienzo, $rutaFinal)) {
			return false;
		}
	} else if ($tipo == 'image/jpeg') { # JPG
		if (!imagejpeg($lienzo, $rutaFinal)) {
			return false;
		}
	} else if ($tipo == 'image/png') { # PNG
		if (!imagepng($lienzo, $rutaFinal)) {
			return false;
		}
	} else {
		return false;
	}
	return true;
}

function saveImage($file, $route, $current_img, $new_name="", $extra="../"){
    
    $imgName = $file['name'];    
    
    $array_elements = array(" ", "/", "!", "?", "¿", "¡", "*", "\'", "'", '"', "$", "%", "@", "&", "¬", "^", "`", "´", "#", "Ñ", "ñ");
    $name_file = str_replace($array_elements, '', $imgName);
    $name_file = normalize($name_file);
    
    $img_new_name = explode(".", $name_file);
    $img_extension = end($img_new_name);
    
    if(!empty($new_name)){
        
        $name_file = $new_name.".".$img_extension;
    }

    if ($current_img != "") {
        
        $direccion_antigua = $extra.$current_img;
        unlink($direccion_antigua);
    }
    // Location 
    $newLocation = $route.$name_file;
    $imageFileType = pathinfo($newLocation, PATHINFO_EXTENSION);
    $imageFileType = strtolower($imageFileType);

    // Upload file 
    if (move_uploaded_file($file['tmp_name'], $extra . $newLocation)) {
        return $newLocation;
    }else{
        return false;
    }
}


function saveDocument($file, $route, $current_doc, $new_name="", $extra="../"){

    $docName = $file['name'];
    
    $array_elements = array(" ", "/", "!", "?", "¿", "¡", "*", "\'", "'", '"', "$", "%", "@", "&", "¬", "^", "`", "´", "#", "Ñ", "ñ");
    $name_file = str_replace($array_elements, '', $docName);
    $name_file = normalize($name_file);
    
    $doc_new_name = explode(".", $name_file);
    $extension = end($doc_new_name);
    
    if(!empty($new_name)){
        
        $name_file = $new_name.".".$extension;
    }

    if ($current_doc != "") {
        
        $direccion_antigua = $extra.$current_doc;
        unlink($direccion_antigua);
    }
    // Location 
    $newLocation = $route.$name_file;

    // Upload file 
    if (move_uploaded_file($file['tmp_name'], $extra . $newLocation)) {

        return $newLocation;
    }else{
        return false;
    }
}

function object_sorter($clave,$orden=null) {
    return function ($a, $b) use ($clave,$orden) {
          $result=  ($orden=="DESC") ? strnatcmp($b->$clave, $a->$clave) :  strnatcmp($a->$clave, $b->$clave);
          return $result;
    };
}

function generateColorRGBA(){    

    $r = mt_rand(128, 255);
    $g = mt_rand(128, 255);
    $b = mt_rand(128, 255);
    $a = '0.5';
    $rgba = 'rgba('.$r.','.$g.','.$b.','.$a.')';

    return $rgba;
}

function generateCodeNumbers($longitud) {
	
	$key = '';
	$pattern = '1234567890';
	$max = strlen($pattern) - 1;
	
	for ($i = 0; $i < $longitud; $i++){
		$key .= $pattern[mt_rand(0, $max)];
	}
	
	return $key;
}

function generarBloque($longitud, $conLetras = false) {
    $caracteres = '0123456789';
    if ($conLetras) {
        $caracteres .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    $bloque = '';
    for ($i = 0; $i < $longitud; $i++) {
        $bloque .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }

    return $bloque;
}

/**
 * Generate hours between ini and end with interval.
 * @param string $hour_ini
 * @param string $hour_end
 * @param int $interval
 * @param string $type
 * @return array
 */
function generateHours($hour_ini, $hour_end, $interval, $type = 'normal') {

	date_default_timezone_set('Europe/Madrid');

	$hours = array();

	$date_ini_hour = new DateTime($hour_ini);
	$date_end_hour = new DateTime($hour_end);
	$date_end_hour->modify('+1 second'); // Adding 1 second for show the correct $hora_fin
	
	// if the ini hour is greater than end hour -> adding one day more to end hour.
	if ($date_ini_hour > $date_end_hour) {

		$date_end_hour->modify('+1 day');
	}

	// Interval in minutes       
	$interval_date = new DateInterval('PT' . $interval . 'M');

	// Period between hours
	$period = new DatePeriod($date_ini_hour, $interval_date, $date_end_hour);

	foreach ($period as $hour) {

		// Save de hours
		if ($type == 'eeuu') {
			$hours[] = $hour->format('G:ia');
		} 
		else if($type == 'three'){
		    
		    $hours[] = $hour->format('H:i:s');
		    
		}
		else {
			$hours[] = $hour->format('H:i');
		}
	}

	return $hours;
}

function _data_last_month_day($month, $year) {
    //$month = date('m');
    //$year = date('Y');
    $day = date("d", mktime(0, 0, 0, $month + 1, 0, $year));

    return date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
}

/** Actual month first day * */
function _data_first_month_day($month, $year) {
    //$month = date('m');
    //$year = date('Y');
    return date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
}

function sendMailDebug($addresses, $subject, $body, $attach = array(), $replyto='') {

	$sendmail = new lmMailer();
        $sendmail->enableDebug();
        //$sendmail->setReplyTo($replyto);
	$sendmail->setAddress($addresses);

	if (!empty($attach)) {

	    $sendmail->setAttachment($attach);
	}

	//$sendmail->setOpen();
	if ($sendmail->sendStandarMail($subject, $body)) {
	    return true;
	} else {

	    $log = new logsModel();
	    $log->setFolder('mails/');
	    $log->setFile_name('sendMail.txt');
	    $log->setType_msg('ERROR');
	    $log->setMsg('Error with sending emails:  EMAILS: ' . json_encode($addresses) . ' Subject: ' . $subject . ' Message: ' . $body);
	    $log->writeLog();
	    return false;
	}


}

function formatReservationDuration(int $minutes): string {

    $hours = intdiv($minutes, 60);
    $remaining_minutes = $minutes % 60;

    if ($hours === 0) {
        return $minutes . ' minutos';
    }

    if ($remaining_minutes === 0) {
        return $hours === 1 ? '1 hora' : $hours . ' horas';
    }

    return ($hours === 1 ? '1 hora' : $hours . ' horas')
            . ' y '
            . $remaining_minutes
            . ' minutos';
}