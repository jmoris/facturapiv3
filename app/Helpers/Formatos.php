<?php
namespace App\Helpers;

class Formatos {

    public static function sanitizarComuna($nombre){
        // 1.- Se quitan todos los acentos
        // 2.- Convertimos el nombre a minusculas
        // 3.- Se transforma en camel case
        // 4.- Se tranforman los 'de' a minusculas

        $nuevoNombre = self::sanitizarString($nombre);
        $nuevoNombre = strtolower($nuevoNombre);
        $nuevoNombre = ucwords($nuevoNombre);
        $nuevoNombre = str_replace(["De", "Del"], ["de", "del"], $nuevoNombre);
        return $nuevoNombre;
    }

    public static function sanitizarString($cadena){
		//Reemplazamos la A y a
		$cadena = str_replace(
		array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª'),
		array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a'),
		$cadena
		);

		//Reemplazamos la E y e
		$cadena = str_replace(
		array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
		array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'),
		$cadena );

		//Reemplazamos la I y i
		$cadena = str_replace(
		array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
		array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'),
		$cadena );

		//Reemplazamos la O y o
		$cadena = str_replace(
		array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
		array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'),
		$cadena );

		//Reemplazamos la U y u
		$cadena = str_replace(
		array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
		array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'),
		$cadena );

		//Reemplazamos la N, n, C y c
		$cadena = str_replace(
		array('Ñ', 'ñ', 'Ç', 'ç'),
		array('N', 'n', 'C', 'c'),
		$cadena
		);
        //Se codifica la cadena para evitar caracteres extraños
        $cadena = mb_convert_encoding($cadena, "ISO-8859-1", "UTF-8");

		return $cadena;
	}

    public static function calcularNetoIVA($total, $tasa = null)
    {
        if ($tasa === 0 or $tasa === false)
            return [0, 0];
        if ($tasa === null)
            $tasa = \SolucionTotal\CoreDTE\Sii::getIVA();
        // WARNING: el IVA obtenido puede no ser el NETO*(TASA/100)
        // se calcula el monto neto y luego se obtiene el IVA haciendo la resta
        // entre el total y el neto, ya que hay casos de borde como:
        //  - BRUTO:   680 => NETO:   571 e IVA:   108 => TOTAL:   679
        //  - BRUTO: 86710 => NETO: 72866 e IVA: 13845 => TOTAL: 86711
        //$neto = round($total / (1+($tasa/100)));
        $neto = round($total / ((100+$tasa) / 100));
        $iva = $total - $neto;
        return [$neto, $iva];
    }
}
