<?php
$_SERVER['HTTP_HOST'] = "localhost";
include_once("../setup.php");
set_time_limit(118640000);

// Affiche les messages de débuggage
$DEBUG = false;
// Affiche plus d'informations à l'écran
$VERBOSE = false;

// Limitation en nb de lignes des traitements de fichier pour le débuggage
$limitation_fichier = 0;

// Désactivation de l'indexation pour la recherche
//define("__CA_DONT_DO_SEARCH_INDEXING__", true);

// Inclusions nécessaires des fichiers de providence
//require_once(__CA_LIB_DIR__.'/core/Db.php');	
include_once(__CA_MODELS_DIR__."/ca_storage_locations.php");	
include_once(__CA_MODELS_DIR__."/ca_objects.php");	
require_once(__CA_MODELS_DIR__."/ca_entities.php");
require_once(__CA_MODELS_DIR__."/ca_users.php");
include_once(__CA_MODELS_DIR__."/ca_lists.php");
include_once(__CA_MODELS_DIR__."/ca_locales.php");
include_once(__CA_MODELS_DIR__."/ca_collections.php");
require_once(__CA_LIB_DIR__.'/core/Parsers/DelimitedDataParser.php');

$t_locale = new ca_locales();
$pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id

include_once("migration_functionlib.php");


$t_list = new ca_lists();

$vn_list_item_type_concept = $t_list->getItemIDFromList('list_item_types', 'concept');
$vn_list_item_label_synonym = $t_list->getItemIDFromList('list_item_label_types', 'uf');
$vn_place_other= $t_list->getItemIDFromList('places_types', 'other');



// Appel des scripts pour les différentes listes

/****************************************************************
 * Appels pour le traitement
 ****************************************************************/
 
//remarque pour lextech : séparation techniques dans dmf_lextech / matériaux dmf_lexmateriaux
traiteFichierDMF("txt/lextech-201009A.txt","lextech","DMF : Liste des techniques",5,$limitation_fichier);
traiteFichierDMF("txt/lextech-201009B.txt","lexmateriaux","DMF : Liste des matériaux",5,$limitation_fichier);
//traiteFichierDMF("lexautr-201009.txt","lexautr","DMF : Liste des auteurs",8,$limitation_fichier);
traiteFichierDMF("txt/lexautrole-201009.txt","lexautrole","DMF : Liste des rôles des auteurs/exécutants",5,$limitation_fichier);
traiteFichierDMF("txt/lexdecv-201009.txt","lexdecv","DMF : Liste des méthodes de collecte, types de sites et lieux géographiques de découverte",5,$limitation_fichier);
traiteFichierDMF("txt/lexdeno-201009.txt","lexdeno","DMF : Liste des dénominations",4,$limitation_fichier);
traiteFichierDMF("txt/lexdomn-20100921.txt","lexdomn","DMF : Liste des domaines",16,$limitation_fichier);
traiteFichierDMF("txt/lexecol-201009.txt","lexecol","DMF : Liste des écoles",5,$limitation_fichier);
traiteFichierDMF("txt/lexepoq-201009.txt","lexepoq","DMF : Liste des époques / styles",4,$limitation_fichier);
traiteFichierDMF("txt/lexgene-201009.txt","lexgene","DMF : Liste des stades de création (genèse des oeuvres)",5,$limitation_fichier);
traiteFichierDMF("txt/lexinsc-201009.txt","lexinsc","DMF : Liste des types d'inscriptions",4,$limitation_fichier);
traiteFichierDMF("txt/lexperi-20100921.txt","lexperi","DMF : Liste des datations en siècle ou millénaire (périodes de création, d'exécution et d'utilisation)",5,$limitation_fichier);
traiteFichierDMF("txt/lexsrep-201009.txt","lexsrep","DMF : Liste des sources de la représentation",5,$limitation_fichier);
traiteFichierDMF("txt/lexstat-201009.txt","lexstat","DMF : Liste des termes autorisés du statut juridique de l'objet",4,$limitation_fichier);
traiteFichierDMF("txt/lexutil-201009.txt","lexutil","DMF : Liste des utilisations - destinations",5,$limitation_fichier);
traiteFichierDMF("txt/lexrepr-201203.txt","lexrepr","DMF : Liste des sujets représentés",5,$limitation_fichier);
traiteFichierLieuDMF("txt/lexlieux-201009.txt","lexlieux",5,$limitation_fichier);
echo "\nC'est fini, il est temps d'aller se coucher maintenant !...\n\n";

/****************************************************************
 * Fonction de traitement des fichiers de liste
 ****************************************************************/
function traiteFichierDMF($t_filename,$t_idno_prefix,$t_list_description,$nb_lignes_vides=0,$ligne_limite=0) {
	global $pn_locale_id, $VERBOSE, $DEBUG;
	global $vn_list_item_type_concept,$vn_list_item_label_synonym;
	global $t_list;
	
	$result= 0;
	$row = 1;
	$parent = array ();
	$nb_tab_pre=0;
	
	$explode_separator_array = array();
	$explode_separator_array[1]["separator"]=" = ";
	$explode_separator_array[1]["label_type"]=$vn_list_item_label_synonym;
	
	if (($handle = fopen($t_filename, "r")) !== FALSE) {
		if (!$vn_list_id=getListID($t_list,"dmf_".$t_idno_prefix,$t_list_description)) {
			print "Impossible de trouver la liste dmf_".$t_idno_prefix." !.\n";
			die();
		} else {
			print "Liste dmf_".$t_idno_prefix." : $vn_list_id\n";
		} 
		$contenu_fichier = file_get_contents($t_filename);
		$total=substr_count($contenu_fichier, "\n");
		$contenu_fichier="";
		
		$data="";
	    $parent_selected=0;				
		
	    while (($data = fgets($handle)) !== FALSE) {
			$libelle = str_replace("\t", "", $data);
			$libelle = str_replace("\r\n", "", $libelle);
			
			// comptage du nb de tabulation pour connaître le terme parent
			$nb_tab = substr_count($data,"\t");
	        $row++;
	        
	        // Si aucune information n'est à afficher, on affiche une barre de progression
	        if ((!$DEBUG) && (!$VERBOSE)) {
	        	show_status($row, $total);
	        }
	        
	        if (($row > $nb_lignes_vides + 1) && ($libelle !="")) {
		        
		        if ($row == $ligne_limite) {
		        	print "limite atteinte : ".$ligne_limite." \n";
		        	break;
		        	//die();
		        }
	
				// si plus d'une tabulation
				if (($nb_tab_pre != $nb_tab) && ($nb_tab > 0)) {
					$parent_selected=$parent[$nb_tab - 1];
				} elseif ($nb_tab == 0) {
					$parent_selected=0;
				}
				
				// débuggage
				if ($DEBUG) print "(".$parent_selected.") ".$nb_tab." ".$libelle;
				
				// insertion dans la liste
				if ($vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,$t_idno_prefix."_".($row-$nb_lignes_vides),$libelle,"",1,0, $parent_selected, null, $explode_separator_array )) {
					//if ($VERBOSE) print "LIST ITEM CREATED : ".$libelle."";
				} else {
					print "LIST ITEM CREATION FAILED : ".$libelle." ";
					die();
				}
	
				//print $nb_tab_pre." ".$nb_tab." - parent :".$parent_selected." ".$lexutil;
				// si au moins 1 tabulation, conservation de l'item pour l'appeler comme parent
				// $vn_item_id=$nb_tab;
		        $parent[$nb_tab]=$vn_item_id;
				
	        }
	         
	        $nb_tab_pre=$nb_tab;
	    }
	    fclose($handle);
	  	if ($VERBOSE) { print "dmf_".$t_idno_prefix." treated.\n";}
	  	$result = true;
	} else {
		print "le fichier n'a pu être ouvert.";
		$result=false;
	} 
	return $result;	
}


/****************************************************************
 * Fonction de traitement du fichier de lieux
 ****************************************************************/
function traiteFichierLieuDMF($t_filename,$t_idno_prefix,$nb_lignes_vides=0,$ligne_limite=0) {
	global $pn_locale_id, $VERBOSE, $DEBUG;
	global $vn_list_item_type_concept,$vn_list_item_label_synonym,$vn_place_other;
	global $t_list;
	
	$result= 0;
	$row = 1;
	$parent = array ();
	$nb_tab_pre=0;
	
	$explode_separator_array = array();
	$explode_separator_array[1]["separator"]=" = ";
	$explode_separator_array[1]["label_type"]=$vn_list_item_label_synonym;
	
	print "traitement des lieux\n";
	
	if (($handle = fopen($t_filename, "r")) !== FALSE) {
		$contenu_fichier = file_get_contents($t_filename);
		$total=substr_count($contenu_fichier, "\n");
		$contenu_fichier="";
		
		$data="";
	    $parent_selected=1;				
		
	    while (($data = fgets($handle)) !== FALSE) {
			$libelle = str_replace("\t", "", $data);
			$libelle = str_replace("\r\n", "", $libelle);
			
			// comptage du nb de tabulation pour connaître le terme parent
			$nb_tab = substr_count($data,"\t");
	        $row++;
	        
	        // Si aucune information n'est à afficher, on affiche une barre de progression
	        if ((!$DEBUG) && (!$VERBOSE)) {
	        	show_status($row, $total);
	        }
	        
	        if (($row > $nb_lignes_vides + 1) && ($libelle !="")) {
		        
		        if ($row == $ligne_limite) {
		        	print "limite atteinte : ".$ligne_limite." \n";
		        	break;
		        	//die();
		        }
	
				// si plus d'une tabulation
				if (($nb_tab_pre != $nb_tab) && ($nb_tab > 0)) {
					$parent_selected=$parent[$nb_tab - 1];
				} elseif ($nb_tab == 0) {
					$parent_selected=1;
				}
				
				// débuggage
				if ($DEBUG) print "(".$parent_selected.") ".$nb_tab." ".$libelle;
				
				// insertion dans la liste
				if ($vn_place_id=getPlaceID($libelle, $t_idno_prefix."_".($row-$nb_lignes_vides), $vn_place_other, $parent_selected, $explode_separator_array)) {
				} else {
					print "PLACE CREATION FAILED : ".$libelle." ";
					die();
				}
	
		        $parent[$nb_tab]=$vn_place_id;
				
	        }
	         
	        $nb_tab_pre=$nb_tab;
	    }
	    fclose($handle);
	  	if ($VERBOSE) { print "dmf_".$t_idno_prefix." treated.\n";}
	  	$result = true;
	} else {
		print "le fichier n'a pu être ouvert.";
		$result=false;
	} 
	return $result;	
}


?>
