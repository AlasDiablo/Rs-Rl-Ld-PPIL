<?php

namespace ppil\controller;

use DateTime;
use ppil\models\Passager;
use ppil\models\Trajet;
use ppil\models\Utilisateur;
use ppil\models\VilleFrance;
use ppil\models\VilleIntermediaire;
use ppil\util\AppContainer;
use ppil\util\EmailFactory;
use ppil\view\RideView;
use ppil\view\ViewRendering;
use ppil\controller\NotificationController;
use ppil\models\Groupe;
use ppil\models\Membre;
use ppil\models\Notification;

class RideController
{

    public static function getRide($id)
    {
        return Trajet::where('id_trajet', '=', $id)->first();
    }

    public static function getEtape($id)
    {
        return Villeintermediaire::where('id_trajet', '=', $id)->get();
    }

    public static function getPassager($id)
    {
        return Passager::where('id_trajet', '=', $id)->get();
    }

    public static function getNbPlaceOccupee($id)
    {
        return count(self::getPassager($id));
    }

    public static function displayRide($id)
    {
        $data = array();
        $ride = self::getRide($id);

        $data['ville_depart'] = $ride->ville_depart;
        $data['ville_arrivee'] = $ride->ville_arrivee;
        $data['nbr_passager'] = $ride->nbr_passager;
        $data['nbr_passager_occup'] = self::getNbPlaceOccupee($id);
        $data['heure_depart'] = $ride->heure_depart;
        $data['prix'] = $ride->prix;
        $data['date'] = $ride->date;
        $data['lieuxRDV'] = $ride->lieuxRDV;
        $data['commentaires'] = $ride->commentaires;
        $data['ville_intermediere'] = self::getEtape($id);
        $data['passagers'] = self::getPassager($id);
        $data['creator'] = $ride->email_conducteur;
        $data['id'] = $id;

        return RideView::renderRide($data);
    }

    public static function creerTrajet()
    {
        $villeDepart = filter_var($_POST['departure'], FILTER_DEFAULT);
        $villeArrivee = filter_var($_POST['arrival'], FILTER_DEFAULT);
        $date = filter_var($_POST['date'], FILTER_DEFAULT);
        $nbPassagers = filter_var($_POST['passengers'], FILTER_DEFAULT);
        $heureDepart = filter_var($_POST['hour'], FILTER_DEFAULT);
        $prix = filter_var($_POST['price'], FILTER_DEFAULT);


        // A changer en fonction de comment les etapes interm??diaires ont ??t?? int??gr?? dans le formulaire (array...)
        $etapeInter = array();
        $i = 0;
        foreach ($_POST['stages'] as $stage) {
            $etapeInter[$i] = filter_var($stage, FILTER_DEFAULT);
            $i++;
        }

        $lieuxRDV = filter_var($_POST['place'], FILTER_DEFAULT);

        $commentaires = filter_var($_POST['comments'], FILTER_DEFAULT);

        $matches = null;

        // Messages d'erreurs pour la ville de d??part
        if (!isset($villeDepart)) {
            return ViewRendering::renderError("Vous n'avez pas mis de ville de d??part.");
        }
        if (preg_match('/^[a-zA-Z]+$/', $villeDepart, $matches, PREG_OFFSET_CAPTURE, 0) == false) {
            return ViewRendering::renderError("Le nom de la ville de d??part ne peut pas comporter de chiffre.");
        }
        if (!isset(VilleFrance::where('ville_nom', '=', $villeDepart)->first()->ville_nom)) {
            return ViewRendering::renderError("La ville de d??part n'existe pas dans la base de donn??es.");
        }

        // Messages d'erreurs pour la ville d'arriv??e
        if (!isset($villeArrivee)) {
            return ViewRendering::renderError("Vous n'avez pas mis de ville d'arriv??e.");
        }
        if (preg_match('/^[a-zA-Z]+$/', $villeArrivee, $matches, PREG_OFFSET_CAPTURE, 0) == false) {
            return ViewRendering::renderError("Le nom de la ville d'arriv??e ne peut pas comporter de chiffre.");
        }
        if (!isset(VilleFrance::where('ville_nom', '=', $villeArrivee)->first()->ville_nom)) {
            return ViewRendering::renderError("La ville d'arriv??e n'existe pas dans la base de donn??es.");
        }

        // Messages d'erreurs pour la date de d??part
        if (!isset($date)) {
            return ViewRendering::renderError("Vous n'avez pas mis de date de d??part.");
        }
        if (!self::validateDateDepart($date, "Y-m-d")) {
            return ViewRendering::renderError("Date de d??part invalide.");
        }

        // Messages d'erreurs pour le nombre de passagers
        if (!isset($nbPassagers)) {
            return ViewRendering::renderError("Vous n'avez pas mis le nombre de passagers pour le trajet.");
        }
        if (preg_match('/^[1-9]+[0-9]*$/', $nbPassagers, $matches, PREG_OFFSET_CAPTURE, 0) == false) {
            return ViewRendering::renderError("Le nombre de passagers doit ??tre un nombre entier.");
        }

        // Messages d'erreurs pour l'heure de d??part
        if (!isset($heureDepart)) {
            return ViewRendering::renderError("Vous n'avez pas mis d'heure de d??part.");
        }
        if (!self::validateDateDepart($date . " " . $heureDepart, "Y-m-d hh:mm")) {
            return ViewRendering::renderError("Heure de d??part invalide.");
        }

        // Messages d'erreurs pour le nombre de passagers
        if (!isset($prix)) {
            return ViewRendering::renderError("Vous n'avez pas mis le nombre de passagers pour le trajet.");
        }
        if (!(filter_var($prix, FILTER_VALIDATE_FLOAT) || filter_var($prix, FILTER_VALIDATE_INT))) {
            return ViewRendering::renderError("Le prix doit ??tre un nombre entier ou reel.");
        }
        if ($prix < 0) {
            return ViewRendering::renderError("Le prix doit ??tre sup??rieur ou egal ?? zero.");
        }

        // Messages d'erreurs pour les etapes interm??diaires
        //        if (isset($etapeInter) && $etapeInter!=""){
        //            if(preg_match('/^[a-zA-Z]+$/', $etapeInter, $matches, PREG_OFFSET_CAPTURE, 0) == false){
        //                return ViewRendering::renderError("Le nom d'une ??tape interm??diaire: " . $etapeInter . " ne peut pas comporter de chiffre.");
        //            }
        //            if(!isset(VilleIntermediaire::where('ville', '=', $etapeInter)->first()->ville_nom)){
        //                return ViewRendering::renderError("L'??tape interm??diaire: " . $etapeInter . " n'existe pas dans la base de donn??es.");
        //            }
        //        }

        $ride = new Trajet();
        $ride->date = $date;
        $ride->ville_depart = $villeDepart;
        $ride->ville_arrivee = $villeArrivee;
        $ride->heure_depart = $heureDepart;
        $ride->email_conducteur = $_SESSION['mail'];
        $ride->nbr_passager = $nbPassagers;
        $ride->prix = $prix;
        $ride->commentaires = $commentaires;
        $ride->lieuxRDV = $lieuxRDV;

        $private = filter_var($_POST['private'], FILTER_DEFAULT);
        $privateGroup = filter_var($_POST['privateGroup'], FILTER_DEFAULT);
        if(isset($private) && $private) {
            if (!isset($privateGroup))
            {
                return ViewRendering::renderError("L'identifiant du groupe n'est pas pr??sent");
            }
            $id_groupe = Groupe::where("id_groupe", "=", $privateGroup)->first();
            if (!isset($id_groupe))
            {
                return ViewRendering::renderError("Le groupe n'existe pas");
            }
            $member = Membre::where("email_membre", "=", $_SESSION['mail'])->where("id_groupe", "=", $privateGroup)->first();
            if (!isset($member))
            {
                return ViewRendering::renderError("Vous n'??tes pas membre du groupe");
            }
            $ride->id_groupe = $privateGroup;
        }

        $id = Trajet::max('id_trajet');
        if (isset($id)) $id++;
        else $id = 0;

        $ride->id_trajet = $id;
        $ride->save();

        foreach ($etapeInter as $etape) {
            if (isset($etape) && $etape != '') {
                $villeIntermediaire = new VilleIntermediaire();
                $villeIntermediaire->id_trajet = $id;
                $villeIntermediaire->ville = $etape;
                $villeIntermediaire->save();
            }
        }

        $url = AppContainer::getInstance()->getRouteCollector()->getRouteParser()->urlFor('ride', ['id' => $id]);
        header("Location: $url");
        exit();
    }

    private static function validateDateDepart($date, $format)
    {
        $date_now = new DateTime();
        return strtotime($date_now->format($format)) <= strtotime($date);
    }

    public static function participate($id)
    {
        // parametre
        $id = filter_var($id, FILTER_DEFAULT);

        // verif trajet existe
        $trajet = Trajet::where('id_trajet', '=', $id)->first();
        if (!isset($trajet)){
            return ViewRendering::renderError("Le trajet n'existe pas/plus.");
        }
        // verif nb place
        if ($trajet->nbr_passager <= self::getNbPlaceOccupee($id)) {
            return ViewRendering::renderError("Plus de place disponible.");
        }
        $mail = $_SESSION['mail'];
        if (!isset($mail)) {
            return ViewRendering::renderError("Vous n'??tes pas connect??.");
        }

        $passager = new Passager();
        $passager->email_passager = $mail;
        $passager->id_trajet = $id;
        $passager->save();

        $mailConducteur = $trajet->email_conducteur;
        $conducteur = Utilisateur::where('email', '=', $mailConducteur)->first();
        if(!isset($conducteur)) {
            return ViewRendering::renderError("Le responsable du trajet est introuvable.");
        }
        /*EmailFactory::envoieEmail("", "Demande de participation ?? votre trajet", $mailConducteur, $conducteur->nom);*/

        // notif et mail
        NotificationController::sendMyParticipationTo($mail, $mailConducteur, $id);

        // redirection
        $url = AppContainer::getInstance()->getRouteCollector()->getRouteParser()->urlFor('participating-rides');
        header("Location: $url");
        exit();
    }

    public static function removeParticipate($id)
    {
        // parametre
        $id = filter_var($id, FILTER_DEFAULT);

        // verif
        $trajet = Trajet::where('id_trajet', '=', $id)->first();
        if (!isset($trajet)){
            return ViewRendering::renderError("Le trajet n'existe pas/plus.");
        }
        $mail = $_SESSION['mail'];
        if (!isset($mail)) {
            return ViewRendering::renderError("Vous n'??tes pas connect??.");
        }
        $mailConducteur = $trajet->email_conducteur;
        $conducteur = Utilisateur::where('email', '=', $mailConducteur)->first();
        if(!isset($conducteur)) {
            return ViewRendering::renderError("Le responsable du trajet est introuvable.");
        }

        // modif
        $passager = Passager::where("email_passager", "=", $mail)->where("id_trajet", "=", $id)->first();
        echo $passager;
        if(!isset($passager))
        {
            return ViewRendering::renderError("Le passsager est introuvable.");
        }
        Passager::where("email_passager", "=", $mail)->where("id_trajet", "=", $id)->delete();

        // notif et mail
        NotificationController::sendMyDismissTo($mail, $mailConducteur, $id);

        // redirection
        $url = AppContainer::getInstance()->getRouteCollector()->getRouteParser()->urlFor('participating-rides');
        header("Location: $url");
        exit();
    }

    public static function deleteRide($id)
    {
        $ride = RideController::getRide($id);
        if(!isset($ride))
        {
            return ViewRendering::renderError("Le trajet n'existe pas");
        }

        setlocale(LC_TIME, "fr_FR");
        $today =  strftime("%B %e, %Y, %H:%M");
        $tommorow = date('Y-m-d', strtotime($today. ' + 1 days'));
        if ($ride->date < $tommorow)
        {
            return ViewRendering::renderError("Impossible de supprimer un trajet qui d??marre dans moins d'un jour ");
        }
        
        $ride->delete();
        
        $url = AppContainer::getInstance()->getRouteCollector()->getRouteParser()->urlFor('myrides');
        header("Location: $url");
        exit();
    }
}
