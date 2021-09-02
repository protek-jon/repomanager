<?php
global $WWW_DIR;
require_once("${WWW_DIR}/class/Database.php");
require_once("${WWW_DIR}/class/Log.php");
require_once("${WWW_DIR}/class/Group.php");
include_once("${WWW_DIR}/class/includes/cleanArchives.php");

class Repo {
    public $db;
    public $id; // l'id en BDD du repo
    public $name;
    public $source;
    public $dist;
    public $section;
    public $date;
    public $dateFormatted;
    public $time;
    public $env;
    public $description;
    public $signed; // yes ou no
    public $type; // miroir ou local

    // Variable supplémentaires utilisées lors d'opérations sur le repo
    public $newName;
    public $newEnv;
    public $sourceFullUrl;
    public $hostUrl;
    public $rootUrl;
    public $gpgCheck;
    public $gpgResign;
    public $log;

    /**
     *  Import des traits nécessaires pour les opérations sur les repos/sections
     */
    use cleanArchives;

    public function __construct(array $variables = []) {
        global $OS_FAMILY;
        global $DEFAULT_ENV;
        global $DATE_YMD;
        global $DATE_DMY;

        extract($variables);

        /**
         *  Instanciation d'une db car on peut avoir besoin de récupérer certaines infos en BDD
         */
        try {
            $this->db = new Database();
        } catch(Exception $e) {
            die('Erreur : '.$e->getMessage());
        }
        
        /* Id */
        if (!empty($repoId)) { $this->id = $repoId; }
        /* Type */
        if (!empty($repoType)) { $this->type = $repoType; }
        /* Nom */
        if (!empty($repoName)) { $this->name = $repoName; }
        /* Nouveau nom */
        if (!empty($repoNewName)) { $this->newName = $repoNewName; }
        /* Distribution (Debian) */
        if (!empty($repoDist)) { $this->dist = $repoDist; }
        /* Section (Debian) */
        if (!empty($repoSection)) { $this->section = $repoSection; }
        /* Env */
        if (empty($repoEnv)) { $this->env = $DEFAULT_ENV; } else { $this->env = $repoEnv; }
        /* New env */
        if (!empty($repoNewEnv)) { $this->newEnv = $repoNewEnv; }
        /* Groupe */
        if (!empty($repoGroup)) { 
            if ($repoGroup == 'nogroup') {
                $this->group = ''; 
            } else { 
                $this->group = $repoGroup; }
            } else { 
                $this->group = '';
            }
        /* Description */
        if (!empty($repoDescription)) {
            if ($repoDescription == 'nodescription') {
                $this->description = '';
            } else {
                $this->description = $repoDescription;
            }
        } else {
            $this->description = '';
        }
        /* Date */
        if (empty($repoDate)) {
            // Si aucune date n'a été transmise alors on prend la date du jour
            $this->date = $DATE_YMD;
            $this->dateFormatted = $DATE_DMY;
        } else { 
            // $repoDate est généralement au format d-m-Y, on le convertit en format DATETIME pour qu'il puisse être facilement inséré en BDD
            $this->date = DateTime::createFromFormat('d-m-Y', $repoDate)->format('Y-m-d');
            $this->dateFormatted = $repoDate;
        }
        /* Time */
        if (empty($repoTime)) { $this->time = exec("date +%H:%M"); } else { $this->time = $repoTime; }

        /* Source */
        if (!empty($repoSource)) {
            $this->source = $repoSource;

            /**
             *  On récupère au passage l'url source complète
             */
            if ($OS_FAMILY == "Debian" AND $this->type == "mirror") {
                $this->getFullSource();
            }
        }
        /* Signed */
        if (!empty($repoSigned)) { $this->signed = $repoSigned; }
        /* Gpg resign */
        if (!empty($repoGpgResign)) {
            $this->signed    = $repoGpgResign;
            $this->gpgResign = $repoGpgResign;
        }
        /* gpg check */
        if (!empty($repoGpgCheck)) { $this->gpgCheck = $repoGpgCheck; }
    }


/**
 *  LISTAGE
 */

/**
 *  Retourne un array de tous les repos/sections
 */
    public function listAll() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $result = $this->db->query("SELECT * FROM repos WHERE Status = 'active' ORDER BY Name ASC, Env ASC");
        }
        if ($OS_FAMILY == "Debian") {
            $result = $this->db->query("SELECT * FROM repos WHERE Status = 'active' ORDER BY Name ASC, Dist ASC, Section ASC, Env ASC");
        }
        while ($datas = $result->fetchArray()) { $repos[] = $datas; }
        if (!empty($repos)) {
            return $repos;
        }
    }

/**
 *  Retourne un array de tous les repos/sections archivé(e)s
 */
    public function listAll_archived() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $result = $this->db->query("SELECT * FROM repos_archived WHERE Status = 'active' ORDER BY Name ASC");
        }
        if ($OS_FAMILY == "Debian") {
            $result = $this->db->query("SELECT * FROM repos_archived WHERE Status = 'active' ORDER BY Name ASC, Dist ASC, Section ASC");
        }
        while ($datas = $result->fetchArray()) { $repos[] = $datas; }
        if (!empty($repos)) {
            return $repos;
        }
    }

/**
 *  Retourne un array de tous les repos/sections (nom seulement)
 */
    public function listAll_distinct() {
        global $OS_FAMILY;
        if ($OS_FAMILY == "Redhat") { $result = $this->db->query("SELECT DISTINCT Name FROM repos WHERE Status = 'active' ORDER BY Name ASC"); }
        if ($OS_FAMILY == "Debian") { $result = $this->db->query("SELECT DISTINCT Name, Dist, Section FROM repos WHERE Status = 'active' ORDER BY Name ASC, Dist ASC, Section ASC"); }
        while ($datas = $result->fetchArray()) { $repos[] = $datas; }
        if (!empty($repos)) {
            return $repos;
        }
    }

/**
 *  Retourne un array de tous les repos/sections (nom seulement), sur un environnement en particulier
 */
    public function listAll_distinct_byEnv(string $env) {
        global $OS_FAMILY;
        if ($OS_FAMILY == "Redhat") { $result = $this->db->query("SELECT DISTINCT Id, Name FROM repos WHERE Env = '$env' AND Status = 'active' ORDER BY Name ASC"); }
        if ($OS_FAMILY == "Debian") { $result = $this->db->query("SELECT DISTINCT Id, Name, Dist, Section FROM repos WHERE Env = '$env' AND Status = 'active' ORDER BY Name ASC, Dist ASC, Section ASC"); }
        while ($datas = $result->fetchArray()) { $repos[] = $datas; }
        if (!empty($repos)) {
            return $repos;
        }
    }

/**
 *  Compter le nombre total de repos/sections
 */
    public function countActive() {
        global $OS_FAMILY;
        if ($OS_FAMILY == "Redhat") { $result = $this->db->countRows("SELECT DISTINCT Name FROM repos WHERE Status = 'active'"); }
        if ($OS_FAMILY == "Debian") { $result = $this->db->countRows("SELECT DISTINCT Name, Dist, Section FROM repos WHERE Status = 'active'"); }
        return $result;
    }

/**
 *  Compter le nombre total de repos/sections archivé(e)s
 */
    public function countArchived() {
        global $OS_FAMILY;
        if ($OS_FAMILY == "Redhat") { $result = $this->db->countRows("SELECT DISTINCT Name FROM repos_archived WHERE Status = 'active'"); }
        if ($OS_FAMILY == "Debian") { $result = $this->db->countRows("SELECT DISTINCT Name, Dist, Section FROM repos_archived WHERE Status = 'active'"); }
        return $result;
    }


/**
 *  VERIFICATIONS
 */

    /**
     *  Vérifie que l'Id du repo existe en BDD
     *  Retourne true si existe
     *  Retourne false si n'existe pas
     */
    public function existsId() {
        if ($this->db->countRows("SELECT * FROM repos WHERE Id = '$this->id' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Vérifie que le repo existe
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function exists(string $name) {
        if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Vérifie que le repo existe, sur un environnement en particulier
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function existsEnv(string $name, string $env) {
        if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Env = '$env' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Vérifie que le repo existe, à une date en particulier
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function existsDate(string $name, string $date, string $status) {
        // Recherche dans la table repos
        if ($status == 'active') {
            if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Date = '$date' AND Status = 'active'") == 0) {
                return false;
            } else {
                return true;
            }
        }
        // Recherche dans la table repos_archived
        if ($status == 'archived') {
            if ($this->db->countRows("SELECT * FROM repos_archived WHERE Name = '$name' AND Date = '$date' AND Status = 'active'") == 0) {
                return false;
            } else {
                return true;
            }
        }
    }

/**
 *  Vérifie que le repo existe, à une date en particulier et à un environnement en particulier
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function existsDateEnv(string $name, string $date, string $env) {
    if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Date = '$date' AND Env = '$env' AND Status = 'active'") == 0) {
        return false;
    } else {
        return true;
    }
}

/**
 *  Vérifie que la distribution existe
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function dist_exists(string $name, string $dist) {
        if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Dist = '$dist' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Vérifie que la section existe
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function section_exists(string $name, string $dist, string $section) {
        if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Dist = '$dist' AND Section = '$section' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Vérifie que la section existe, sur un environnement en particulier
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function section_existsEnv(string $name, string $dist, string $section, string $env) {
        if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Dist = '$dist' AND Section = '$section' AND Env = '$env' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Vérifie que la section existe, à une date en particulier
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function section_existsDate(string $name, string $dist, string $section, string $date, string $status) {
        // Recherche dans la table repos
        if ($status == 'active') {
            if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Dist = '$dist' AND Section = '$section' AND Date = '$date' AND Status = 'active'") == 0) {
                return false;
            } else {
                return true;
            }
        }
        // Recherche dans la table repos_archived
        if ($status == 'archived') {
            if ($this->db->countRows("SELECT * FROM repos_archived WHERE Name = '$name' AND Dist = '$dist' AND Section = '$section' AND Date = '$date' AND Status = 'active'") == 0) {
                return false;
            } else {
                return true;
            }
        }        
    }

/**
 *  Vérifie que la section existe, à une date en particulier et à un environnement en particulier
 *  Retourne true si existe
 *  Retourne false si n'existe pas
 */
    public function section_existsDateEnv(string $name, string $dist, string $section, string $date, string $env) {
        if ($this->db->countRows("SELECT * FROM repos WHERE Name = '$name' AND Dist = '$dist' AND Section = '$section' AND Date = '$date' AND Env = '$env' AND Status = 'active'") == 0) {
            return false;
        } else {
            return true;
        }
    }

/**
 *  Récupère l'ID du repo/de la section en BDD
 */
    public function db_getId() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $stmt = $this->db->prepare("SELECT Id from repos WHERE Name=:name AND Env =:env AND Status = 'active'");
        }

        if ($OS_FAMILY == "Debian") {
            $stmt = $this->db->prepare("SELECT Id from repos WHERE Name=:name AND Dist=:dist AND Section=:section AND Env=:env AND Status = 'active'");
        }

        $stmt->bindValue(':name', $this->name);
        if ($OS_FAMILY == "Debian") {
            $stmt->bindValue(':dist', $this->dist);
            $stmt->bindValue(':section', $this->section);
        }
        $stmt->bindValue(':env', $this->env);
        $result = $stmt->execute();

        while ($row = $result->fetchArray()) {
            $this->id = $row['Id'];
        }

        unset($stmt, $result);
    }

    /**
     *  Comme au dessus mais pour un repo/section archivé
     */
    public function db_getId_archived() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $stmt = $this->db->prepare("SELECT Id from repos_archived WHERE Name=:name AND Date=:date AND Status = 'active'");
        }

        if ($OS_FAMILY == "Debian") {
            $stmt = $this->db->prepare("SELECT Id from repos_archived WHERE Name=:name AND Dist=:dist AND Section=:section AND Date=:date AND Status = 'active'");
        }

        $stmt->bindValue(':name', $this->name);
        if ($OS_FAMILY == "Debian") {
            $stmt->bindValue(':dist', $this->dist);
            $stmt->bindValue(':section', $this->section);
        }
        $stmt->bindValue(':date', $this->date);
        $result = $stmt->execute();

        while ($row = $result->fetchArray()) {
            $this->id = $row['Id'];
        }

        unset($stmt, $result);
    }

/**
 *  Recupère toutes les information du repo/de la section en BDD à partie de son ID
 */
    public function db_getAllById(string $type = '') {
        global $OS_FAMILY;

        /**
         *  Si on a précisé un type en argument et qu'il est égal à 'archived' alors on interroge la table des repos archivé
         *  Sinon dans tous les autres cas on interroge la table par défaut càd les repos actifs
         */
        if (!empty($type) AND $type == 'archived') {
            $stmt = $this->db->prepare("SELECT * from repos_archived WHERE Id=:id");
        } else {
            $stmt = $this->db->prepare("SELECT * from repos WHERE Id=:id");
        }
        $stmt->bindValue(':id', $this->id);
        $result = $stmt->execute();

        while ($row = $result->fetchArray()) {
            $this->name = $row['Name'];
            if ($OS_FAMILY == 'Debian') {
                $this->dist = $row['Dist'];
                $this->section = $row['Section'];
            }
            $this->source = $row['Source'];
            if ($OS_FAMILY == "Debian" AND $this->type == "mirror") {
                $this->getFullSource();
            }
            $this->date = $row['Date'];
            $this->dateFormatted = DateTime::createFromFormat('Y-m-d', $row['Date'])->format('d-m-Y');
            if (!empty($row['Env'])) $this->env = $row['Env']; // Dans le cas où on a précisé $type == 'archived' il n'y a pas d'env pour les repo archivés, d'où la condition
            $this->type = $row['Type'];
            $this->signed = $row['Signed']; $this->gpgResign = $this->signed;
            $this->description = $row['Description'];
        }

        unset($stmt, $result);
    }

/**
 *  Recupère toutes les information du repo/de la section en BDD à partir de son nom et son env
 */
    public function db_getAll() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $result = $this->db->query("SELECT * from repos WHERE Name = '$this->name' AND Env = '$this->env' AND Status = 'active'");
        }

        if ($OS_FAMILY == "Debian") {
            $result = $this->db->query("SELECT * from repos WHERE Name = '$this->name' AND Dist = '$this->dist' AND Section = '$this->section' AND Env = '$this->env' AND Status = 'active'");
        }
        while ($row = $result->fetchArray()) {
            $this->id = $row['Id'];
            $this->source = $row['Source'];
            $this->date = $row['Date'];
            $this->dateFormatted = DateTime::createFromFormat('Y-m-d', $row['Date'])->format('d-m-Y');
            $this->type = $row['Type'];
            $this->signed = $row['Signed'];
            $this->description = $row['Description'];
        }
    }
/**
 *  Récupère la date du repo/section en BDD
 */
    public function db_getDate() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $result = $this->db->querySingleRow("SELECT Date from repos WHERE Name = '$this->name' AND Env = '$this->env' AND Status = 'active'");
        }

        if ($OS_FAMILY == "Debian") {
            $result = $this->db->querySingleRow("SELECT Date from repos WHERE Name = '$this->name' AND Dist = '$this->dist' AND Section = '$this->section' AND Env = '$this->env' AND Status = 'active'");
        }
        $this->date = $result['Date'];
        $this->dateFormatted = DateTime::createFromFormat('Y-m-d', $result['Date'])->format('d-m-Y');
    }

/**
 *  Recupère la source du repo/section en BDD
 */
    public function db_getSource() {
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            $result = $this->db->querySingleRow("SELECT Source from repos WHERE Name = '$this->name' AND Status = 'active'");
        }

        if ($OS_FAMILY == "Debian") {
            $result = $this->db->querySingleRow("SELECT Source from repos WHERE Name = '$this->name' AND Dist = '$this->dist' AND Section = '$this->section' AND Status = 'active'");
        }
        if (empty($result['Source'])) {
            throw new Exception("<br><span class=\"redtext\">Erreur : </span>impossible de déterminer la source de du repo <b>$this->name</b>");
        }
        $this->source = $result['Source'];

        /**
         *  On récupère au passage l'url source complète
         */
        if ($OS_FAMILY == "Debian") {
            $this->getFullSource();
        }
    }

/**
 *  Récupère l'url source complete avec la racine du dépot (Debian uniquement)
 */
    private function getFullSource() {
        /**
         *  Récupère l'url complète
         */
        $result = $this->db->querySingleRow("SELECT Url FROM sources WHERE Name = '$this->source'");
        $this->sourceFullUrl = $result['Url'];
        $this->hostUrl = exec("echo '$this->sourceFullUrl' | cut -d'/' -f1");
        
        /**
         *  Extraction de la racine de l'hôte (ex pour : ftp.fr.debian.org/debian ici la racine sera debian)
         */
        $this->rootUrl = exec("echo '$this->sourceFullUrl' | sed 's/$this->hostUrl//g'");
        if (empty($this->hostUrl)) {
            throw new Exception('<br><span class="redtext">Erreur : </span>impossible de déterminer l\'adresse de l\'hôte source');
        }
        if (empty($this->rootUrl)) {
            throw new Exception('<br><span class="redtext">Erreur : </span>impossible de déterminer la racine de l\'URL hôte');
        }
    }

/**
 *  MODIFICATION DES INFORMATIONS DU REPO
 */
    public function edit() {
        $this->db->exec("UPDATE repos SET Description = '$this->description' WHERE Id = '$this->id'");
        printAlert('Modifications prises en compte');
    }

/**
 *  GENERATION DE CONF
 */
    public function generateConf(string $destination) {
        global $REPOS_PROFILES_CONF_DIR;
        global $REPO_CONF_FILES_PREFIX;
        global $WWW_HOSTNAME;
        global $GPG_SIGN_PACKAGES;
        global $OS_FAMILY;

        // On peut préciser à la fonction le répertoire de destination des fichiers. Si on précise une valeur vide ou bien "default", alors les fichiers seront générés dans le répertoire par défaut
        if (empty($destination) OR $destination == "default") {
            $destination = $REPOS_PROFILES_CONF_DIR;
        }

        // Génération du fichier pour Redhat/Centos
        if ($OS_FAMILY == "Redhat") {
            $content = "# Repo {$this->name} sur ${WWW_HOSTNAME}";
            $content = "${content}\n[${REPO_CONF_FILES_PREFIX}{$this->name}___ENV__]";
            $content = "${content}\nname=Repo {$this->name} sur ${WWW_HOSTNAME}";
            $content = "${content}\ncomment=Repo {$this->name} sur ${WWW_HOSTNAME}";
            $content = "${content}\nbaseurl=https://${WWW_HOSTNAME}/repo/{$this->name}___ENV__";
            $content = "${content}\nenabled=1";
            if ($GPG_SIGN_PACKAGES == "yes") {
            $content = "${content}\ngpgcheck=1";
            $content = "${content}\ngpgkey=https://${WWW_HOSTNAME}/repo/${WWW_HOSTNAME}.pub";
            } else {
            $content = "${content}\ngpgcheck=0";
            }
            // Création du fichier si n'existe pas déjà
            if (!file_exists("${destination}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo")) {
            touch("${destination}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo");
            }
            // Ecriture du contenu dans le fichier
            file_put_contents("${destination}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo", $content);
        }
        // Génération du fichier pour Debian
        if ($OS_FAMILY == "Debian") {
            $content = "# Repo {$this->name}, distribution {$this->dist}, section {$this->section} sur ${WWW_HOSTNAME}";
            $content = "${content}\ndeb https://${WWW_HOSTNAME}/repo/{$this->name}/{$this->dist}/{$this->section}___ENV__ {$this->dist} {$this->section}";
            
            // Si le nom de la distribution contient un slash, c'est le cas par exemple avec debian-security (buster/updates), alors il faudra remplacer ce slash par [slash] dans le nom du fichier .list 
            $checkIfDistContainsSlash = exec("echo $this->dist | grep '/'");
            if (!empty($checkIfDistContainsSlash)) {
            $repoDistFormatted = str_replace("/", "[slash]","$this->dist");
            } else {
            $repoDistFormatted = $this->dist;
            }
            // Création du fichier si n'existe pas déjà
            if (!file_exists("${destination}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list")) {
            touch("${destination}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list");
            }
            // Ecriture du contenu dans le fichier
            file_put_contents("${destination}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list", $content);
        }

        unset($content);
        return 0;
    }

/**
 *  SUPPRESSION DE CONF
 */
    public function deleteConf() {
        global $REPOS_PROFILES_CONF_DIR;
        global $REPO_CONF_FILES_PREFIX;
        global $PROFILES_MAIN_DIR;
        global $PROFILE_SERVER_CONF;
        global $OS_FAMILY;

        if ($OS_FAMILY == "Redhat") {
            // Suppression du fichier si existe
            if (file_exists("${REPOS_PROFILES_CONF_DIR}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo")) {
                unlink("${REPOS_PROFILES_CONF_DIR}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo");
            }

            // Suppression des liens symboliques pointant vers ce repo dans les répertoires de profils 
            $profilesNames = scandir($PROFILES_MAIN_DIR); // Récupération de tous les noms de profils
            foreach($profilesNames as $profileName) {
                if (($profileName != "..") AND ($profileName != ".") AND ($profileName != "_configurations") AND ($profileName != "_reposerver") AND ($profileName != "${PROFILE_SERVER_CONF}")) {
                    if (is_link("${PROFILES_MAIN_DIR}/${profileName}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo")) {
                    unlink("${PROFILES_MAIN_DIR}/${profileName}/${REPO_CONF_FILES_PREFIX}{$this->name}.repo");
                    }
                }
            }
        }

        if ($OS_FAMILY == "Debian") {
            // Si le nom de la distribution contient un slash, c'est le cas par exemple avec debian-security (buster/updates), alors il faudra remplacer ce slash par [slash] dans le nom du fichier .list 
            $checkIfDistContainsSlash = exec("echo $this->dist | grep '/'");
            if (!empty($checkIfDistContainsSlash)) {
                $repoDistFormatted = str_replace("/", "[slash]", $this->dist);
            } else {
                $repoDistFormatted = $this->dist;
            }

            // Suppression du fichier si existe
            if (file_exists("${REPOS_PROFILES_CONF_DIR}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list")) {
                unlink("${REPOS_PROFILES_CONF_DIR}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list");
            }
            
            // Suppression des liens symboliques pointant vers ce repo dans les répertoires de profils 
            $profilesNames = scandir($PROFILES_MAIN_DIR); // Récupération de tous les noms de profils
            foreach($profilesNames as $profileName) {
                if (($profileName != "..") AND ($profileName != ".") AND ($profileName != "_configurations") AND ($profileName != "_reposerver") AND ($profileName != "${PROFILE_SERVER_CONF}")) {
                    if (is_link("${PROFILES_MAIN_DIR}/${profileName}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list")) {
                        unlink("${PROFILES_MAIN_DIR}/${profileName}/${REPO_CONF_FILES_PREFIX}{$this->name}_${repoDistFormatted}_{$this->section}.list");
                    }
                }
            }
        }
    }
}
?>