<?PHP
//creating this file to consolidate functions instead of going to multiple pages for edits

/*
###############################################################################
###############################################################################
#####################         CSR Functions          ##########################
###############################################################################
*/

function create_config($config, $cn){
    //this function will create the custom config file allowing for multiple subject alternative names such as wildcard domains
    print("Creating Custom OpenSSL config file");
    $template = $config['config_dir'] . $cn . "-openssl.conf";
    copy($config['ca_path'] .  "openssl.conf", $template);
    
    return $template;
}

function create_csr($my_cert_dn, $my_keysize, $my_passphrase, $my_device_type)
{
    //this needs to be broken into multiple functions to reduce code
    $config = $_SESSION['config'];
    // update the conf
    $configfile = create_config($config,$_POST['cert_dn']['commonName']);
    //

    //if there are subject alternative names then update the config file to include
    //lines for SAN

    if (isset($_POST['san'])) {
        $san_list = explode("\n", trim($_POST['san']));
        create_conf($san_list, $configfile);
    }

    //reset default config file to new domain specific config file
    $config['config'] = $configfile;

    $cert_dn = array();

    print "<h1>Creating Certificate Key</h1>";
    print "PASSWORD:" . $my_passphrase . "<BR>";

    foreach ($my_cert_dn as $key => $val) {
        if (array_key_exists($key, $my_cert_dn))
            if (strlen($my_cert_dn[$key]) > 0) {
                if($key != "keySize"){
                    $cert_dn[$key] = $my_cert_dn[$key];
                }
                
            }
    }

    $my_csrfile = "";
    foreach ($config['blank_dn'] as $key => $value) {
        if (isset($cert_dn[$config['convert_dn'][$key]]))
            $my_csrfile = $my_csrfile . $cert_dn[$config['convert_dn'][$key]] . ":";
        else
            $my_csrfile = $my_csrfile . ":";
    }

    $my_csrfile = substr($my_csrfile, 0, strrpos($my_csrfile, ':'));

    $filename = base64_encode($my_csrfile);
    print "CSR Filename : " . $my_csrfile . "<BR>";

    if ($my_device_type == 'ca_cert') {
        $client_keyFile = $config['cakey'];
        $client_reqFile = $config['req_path'] . $filename . ".pem";
    } else {
        $client_keyFile = $config['key_path'] . $filename . ".pem";
        $client_reqFile = $config['req_path'] . $filename . ".pem";
    }

    print "<h1>Creating Client CSR and Client Key</h1>";

    print "<b>Checking your DN (Distinguished Name)...</b><br/>";
    
    $my_new_config = array('config' => $config['config'], 'private_key_bits' => (int)$my_keysize);
    
    $privkey = openssl_pkey_new($my_new_config) or die('Fatal: Error creating Certificate Key');
    
    print "Done<br/><br/>\n";

    if ($my_device_type == 'ca_cert') {
        print "<b>Exporting encoded private key to CA Key file...</b><br/>";
    } else {
        print "<b>Exporting encoded private key to file...</b><br/>";
    }
    openssl_pkey_export_to_file($privkey, $client_keyFile, $my_passphrase) or die('Fatal: Error exporting Certificate Key to file');


    print "Done<br/><br/>\n";

    print "<b>Creating CSR...</b><br/>";
  
    /*
    $cert_dn
    Array
(
    [commonName] => www.example3.com
    [emailAddress] => cert@example.com
    [organizationalUnitName] => Device
    [organizationName] => Example.com
    [localityName] => Abita
    [stateOrProvinceName] => Louisiana
    [countryName] => US
    [keySize] => 2048bits
)
    */
    $my_csr = openssl_csr_new($cert_dn, $privkey, $config) or die('Fatal: Error creating CSR');
    //openssl req -new -sha256 -key example_com.key -out example_com.csr -config C:\WampDeveloper\Config\Apache\openssl.cnf
    print "Done<br/><br/>\n";

    print "<b>Exporting CSR to file...</b><br/>";
    openssl_csr_export_to_file($my_csr, $client_reqFile, FALSE) or die('Fatal: Error exporting CSR to file');
    
    print "Done<br/><br/>\n";

    $my_details = openssl_csr_get_subject($my_csr);
    $my_public_key_details = openssl_pkey_get_details(openssl_csr_get_public_key($my_csr));

    // print_r($my_details);
    print "<h1>Client CSR and Key - Generated successfully</h1>";

    // print($my_public_key_details['key']);
    $data = openssl_public_decrypt($my_public_key_details['key'],$finaltext, $my_public_key_details['key']);

    return array($my_details,$my_public_key_details);
}


//create openssl config file
function create_conf($san_list,$configfile){
    $config = $_SESSION['config'];
    //get contents of the openssl.conf template
    $openssl_conf_array = parse_ini_file($config['config'], true);
    $newconfig = "";

    foreach($openssl_conf_array as $key => $val){
      
        
        if($key == " v3_req "){
            //if sub alt names then add line
            $newconfig .= "[$key]" . "\n";
            if(strlen($san_list[0]) >= 3){
                foreach($val as $key2 => $val2){
                  $newconfig .= $key2 . " = " . $val2 . "\n";
                }
                $newconfig .= "subjectAltName = @subject_alt_names";
                $newconfig .= "\n";
  
                $newconfig .= "\n\n[subject_alt_names]\n";
                $san_list_formated = "";
                for($i=0; $i<count($san_list); $i++){
                    $index = $i + 1;
                    $san_list_formated .= 'DNS.' . $index . " = " . $san_list[$i] . "\n";
                    
                  }
                  $newconfig .= $san_list_formated;
            } else {
                foreach($val as $key2 => $val2){
                  $newconfig .= $key2 . " = " . $val2 . "\n";
                }
                $newconfig .= "\n";
            }
          
          
          }elseif($key == " req_ext "){
              if(strlen($san_list[0]) >= 3){
                $newconfig .= "\n[ req_ext ] \n";
                $newconfig .= "subjectAltName          = @subject_alt_names\n";
              }      
        } else {
          $newconfig .= "[$key]" . "\n";
            foreach($val as $key2 => $val2){
              if($key2 == "nsComment"){
                $newconfig .= $key2 . " = " . $_SESSION['my_ca'] . " " . $val2 . "\n";
              }elseif($key2 == "subjectAltName" && $val2 == "@subject_alt_names" && strlen($san_list[0]) < 3){
                $newconfig .= $key2 . " = email:copy";
              } else {
                $newconfig .= $key2 . " = " . $val2 . "\n";
              }
              
            }
        }
        
        $newconfig .= "\n";
    }
  
  //write new config
  file_put_contents($configfile, $newconfig);
  }


  //Download CSR
  function download_csr($this_cert, $cer_ext)
{
  $config = $_SESSION['config'];
  if (!isset($cer_ext))
    $cer_ext = 'FALSE';

  if ($this_cert == "zzTHISzzCAzz") {
    $my_x509_parse = openssl_x509_parse(file_get_contents($config['cacert']));
    $filename = $my_x509_parse['subject']['CN'] . ":" . $my_x509_parse['subject']['OU'] . ":" . $my_x509_parse['subject']['O'] . ":" . $my_x509_parse['subject']['L'] . ":" . $my_x509_parse['subject']['ST'] . ":" . $my_x509_parse['subject']['C'];
    $download_certfile = $config['cacert'];
    $ext = ".pem";
    //$application_type="application/x-x509-ca-cert";
    $application_type = 'application/octet-stream';
  } else {
    $filename = substr($this_cert, 0, strrpos($this_cert, '.'));
    $ext = substr($this_cert, strrpos($this_cert, '.'));
    $download_certfile = base64_encode($filename);
    $download_certfile = $config['req_path'] . $download_certfile . $ext;
    $application_type = 'application/octet-stream';
  }
  if ($cer_ext != 'FALSE')
    $ext = '.' . $cer_ext;

  if (file_exists($download_certfile)) {
    $myCert = join("", file($download_certfile));
    download_header_code($filename . $ext, $myCert, $application_type);
  } else {
    printHeader("Certificate Retrieval");
    print "<h1> $filename - X509 CA certificate not found</h1>\n";
    printFooter(FALSE);
  }
}
?>