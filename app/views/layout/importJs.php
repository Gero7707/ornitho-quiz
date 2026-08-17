<?php
if(isset( $loadScriptJs)){
    if(is_array($loadScriptJs)){
        foreach($loadScriptJs as $jsFile){
            echo '<script src="/assets/js/'. htmlspecialchars($jsFile) . '"></script>'  . PHP_EOL;
        }
    }else{
        echo '<script src="/assets/js/'. htmlspecialchars($loadScriptJs) . '"></script>'  . PHP_EOL;
    }
}
?>