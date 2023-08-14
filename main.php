<?php

require_once 'Potracio.php';
use Potracio\Potracio as Potracio;

function onConvert($minputfile, $moutputfile)
{
   // var_dump($minputfile, $moutputfile);
   $inputfile = $minputfile;
   $filename = $moutputfile;

   $pot = new Potracio();
   $pot->loadImageFromFile($inputfile);
   $pot->process();
  

   $content = '';
   // $content = $content . $pot->getSVG(3, "curve");
   $content = $content . $pot->getSVG(3);

   // var_dump("svg_content:",$content);
   
   file_put_contents($filename, $content);
   
   return "file create successfully";

}

// var_dump("hello");
// echo "<script>console.log('Debug Objects:' );</script>";


header('Content-Type: application/json');

$aResult = array();

if (!isset($_POST['functionname'])) {
   $aResult['error'] = 'No function name!';
}

if (!isset($_POST['arguments'])) {
   $aResult['error'] = 'No function arguments!';
}
if (!isset($aResult['error'])) {
   switch ($_POST['functionname']) {
      case 'onConvert':
            $aResult['result'] = onConvert(strval($_POST['arguments'][0]),strval($_POST['arguments'][1]));
            break;
            
            default:
            $aResult['error'] = 'Not found function ' . $_POST['functionname'] . '!';
            break;
         }
         echo json_encode($aResult);
      }




