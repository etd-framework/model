<?php
/**
 * Part of the ETD Framework Model Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Model;

use EtdSolutions\Form\Form;
use EtdSolutions\Language\LanguageFactory;
use EtdSolutions\Table\Table;
use Joomla\Application\AbstractApplication;
use Joomla\Database\DatabaseInterface;
use Joomla\Form\FormHelper;
use Joomla\Registry\Registry;

/**
 * Modèle pour gérer un élément.
 */
abstract class ItemModel extends Model {

    /**
     * Contexte dans lequel le modèle est instancié.
     *
     * @var    string
     */
    protected $context = null;

    /**
     * Cache interne des données.
     *
     * @var array
     */
    protected $cache = array();

    /**
     * Groupe dans lequel les infos sont stockées en cache.
     *
     * @var    string
     */
    protected $cachegroup = null;

    /**
     * Les Conditions de sélection et de tri des lignes imbriquées.
     *
     * @var array
     */
    protected $reorderConditions = null;

    /**
     * Instancie le modèle.
     *
     * @param AbstractApplication $app            L'objet Application.
     * @param DatabaseInterface   $db             L'objet DatabaseInterface.
     * @param Registry            $state          L'état du modèle.
     * @param bool                $ignore_request Utilisé pour ignorer la mise à jour de l'état depuis la requête.
     */
    public function __construct(AbstractApplication $app, DatabaseInterface $db, Registry $state = null, $ignore_request = false) {

        parent::__construct($app, $db, $state, $ignore_request);

        // On devine le contexte suivant le nom du modèle.
        if (empty($this->context)) {
            $this->context = strtolower($this->getName());
        }

        // On devine le groupe de cache suivant le nom du modèle.
        if (empty($this->cachegroup)) {
            $this->cachegroup = strtolower($this->getName());
        }
    }

    /**
     * Renvoi les données d'un élément à charger en BDD.
     *
     * @param mixed $id Si null, l'id est chargé dans l'état.
     *
     * @return \stdClass Un objet représentant l'élément.
     */
    public function getItem($id = null) {

        $id    = (!empty($id)) ? $id : (int)$this->get($this->context . '.id');
        $table = $this->getTable();

        if ($id > 0) {

            $container = $this->getContainer();
            if ($container->has('cache')) {

                $cache   = $container->get('cache');
                $storeid = $this->getStoreId($id);

                $item = $cache->get($storeid, $this->context);
                if (!isset($item)) {

                    // On charge l'élément.
                    $item = $this->_getItem($id);

                    // On stoke l'élément dans le cache.
                    $cache->set($item, $storeid, $this->context);

                }
            } else { // On charge l'élément.
                $item = $this->_getItem($id);
            }


        } else {
            $item = $table->dump();
        }

        return $item;

    }

    /**
     * Méthode pour charger un enregistrement depuis la base de données.
     *
     * @param $id
     *
     * @return bool|\stdClass
     */
    protected function _getItem($id) {

        $table = $this->getTable();

        // On tente de charger la ligne.
        $return = $table->load($id);

        // On contrôle les erreurs.
        if ($return === false && $table->getError()) {
            $this->setError($table->getError());

            return false;
        }

        // On récupère les données de l'élément.
        $item = $table->dump();

        // On transforme le champ params JSON en tableau.
        if (isset($item->params) && is_string($item->params)) {
            $reg          = new Registry($item->params);
            $item->params = $reg->toArray();
        }

        return $item;

    }

    /**
     * Donne le formulaire associé au modèle.
     *
     * @param null  $name
     * @param array $options
     *
     * @return Form
     * @throws \RuntimeException
     */
    public function getForm($name = null, array $options = array()) {

        $text = (new LanguageFactory())->getText();

        if (!isset($name)) {
            $name = strtolower($this->getName());
        }

        // On met le nom dans les options.
        $options['name'] = $name;

        // On compile un identifiant de cache.
        $store = md5("getForm:" . serialize($options));

        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }

        if (!isset($options['control'])) {
            $options['control'] = 'etdform';
        }

        // On instancie le formulaire.
        $form = new Form($name, $options);
        $form->setContainer($this->getContainer());
        $form->setText($text);
        $form->setDb($this->db);
        $form->setApplication($this->app);

        // On ajoute le chemin vers les fichiers XML des formulaires.
        FormHelper::addFormPath(JPATH_FORMS);

        // On charge les champs depuis le XML.
        if (!$form->loadFile($name)) {
            throw new \RuntimeException($text->sprintf('APP_ERROR_FORM_NOT_LOADED', $name), 500);
        }

        // On charge les données si nécessaire.
        $data = $this->loadFormData($options);

        // On modifie le formulaire si besoin.
        $form = $this->preprocessForm($form, $data);

        // On les lie au formulaire.
        if (!empty($data)) {
            $form->bind($data);
        }

        // On ajoute l'élement au cache.
        $this->cache[$store] = $form;

        return $this->cache[$store];

    }

    /**
     * Méthode pour valider les données en entrée suivant les règles du formulaire associé au modèle.
     *
     * @param $data array Les données à valider.
     *
     * @return boolean True si valide, false sinon.
     */
    public function validate($data) {

        $form = $this->getForm();
        $ret  = $form->validate($data);

        // Si le form n'est pas valide, on stocke les erreurs dans le modèle.
        if ($ret === false) {
            $this->setErrors($form->getErrors());
        }

        return $ret;
    }

    /**
     * Méthode pour ne valider qu'un seul champ suivant les règles du formulaire associé au modèle.
     *
     * @param $name  string Le nom du champ.
     * @param $data  mixed Les données à tester.
     * @param $group  string Le nom du groupe.
     *
     * @return boolean True si valide, false sinon.
     */
    public function validateField($name, $data, $group = null ) {

        $form = $this->getForm();
        $ret  = $form->validate($data, $group, $name);

        // Si le champ n'est pas valide, on stocke les erreurs dans le modèle.
        if ($ret === false) {
            $this->setErrors($form->getErrors());
        }

        return $ret;

    }

    /**
     * Méthode pour filtrer les données en entrée suivant le formulaire associé au modèle.
     *
     * @param $data array Données à filtrer.
     *
     * @return array Les données filtrées.
     */
    public function filter($data) {

        $form = $this->getForm();
        $data = $form->filter($data);

        return $data;

    }

    /**
     * Méthode pour supprimer des enregistrements.
     *
     * @param $pks array|int Un tableau de clés primaires ou une clé primaire.
     *
     * @return bool True si
     */
    public function delete(&$pks) {

        $text = (new LanguageFactory())->getText();

        // On s'assure d'avoir un tableau.
        $pks = (array)$pks;

        // On récupère le table.
        $table = $this->getTable();

        // On supprime tous les éléments.
        foreach ($pks as $i => $pk) {

            if (!$table->delete($pk)) {
                $this->setError($text->translate('APP_ERROR_MODEL_UNABLE_TO_DELETE_ITEM'));

                return false;
            }

            // On nettoie le cache.
            $this->cleanCache($pk);

        }

        return true;

    }

    /**
     * Méthode pour enregistrer les données du formulaire.
     *
     * @param   array $data Les données du formulaire.
     *
     * @return  boolean  True en cas de succès, false sinon.
     */
    public function save($data) {

        // On récupère le table et le nom de la clé primaire.
        $table = $this->getTable();
        $key   = $table->getPk();

        // On récupère la clé primaire.
        $pk = (!empty($data[$key])) ? $data[$key] : (int)$this->get($this->context . '.id');

        // Par défaut, on crée un nouvel enregistrement.
        $isNew = true;

        // On charge la ligne si c'est un enregistrement existant.
        if ($pk > 0) {
            $table->load($pk);
            $isNew = false;
        }

        // On prépare le table avant de lier les données.
        $this->beforeTableBinding($table, $data, $isNew);

        // On relie les données
        if (!$table->bind($data)) {
            $this->setError($table->getError());

            return false;
        }

        // On prépare la ligne avant de la sauvegarder.
        $table = $this->preprocessTable($table);

        // On contrôle les données.
        if (!$table->check()) {
            $this->setError($table->getError());

            return false;
        }

        // On stocke les données.
        if (!$table->store()) {
            $this->setError($table->getError());

            return false;
        }

        // On met à jour l'état du modèle.
        $this->__state_set = true;

        $pkName = $table->getPk();
        if (isset($table->$pkName)) {
            $this->set($this->context . '.id', $table->$pkName);
        }
        $this->set($this->context . '.isNew', $isNew);

        // On nettoie le cache.
        $this->cleanCache($table->$pkName);

        return true;

    }

    /**
     * Méthode pour dupliquer un enregistrement.
     *
     * @param $pks array Un tableau des clés primaires représentantes des enregistrements à dupliquer.
     *
     * @return bool
     */
    public function duplicate($pks) {

        // On s'assure d'avoir un tableau.
        $pks = (array)$pks;

        // On récupère le table.
        $table = $this->getTable();

        // On supprime tous les éléments.
        foreach ($pks as $i => $pk) {

            // On tente de charger la ligne.
            if ($table->load($pk) === false) {
                $this->setError($table->getError());

                return false;
            }

            // On retire la clé primaire pour créer une nouvelle ligne.
            $table->{$table->getPk()} = null;

            // On change les champs.
            $this->prepareDuplicatedTable($table);

            // On contrôle les données.
            if (!$table->check()) {
                $this->setError($table->getError());

                return false;
            }

            // On stocke les données.
            if (!$table->store()) {
                $this->setError($table->getError());

                return false;
            }

            $this->afterDuplicatedTable($table, $pk);

        }

        // On nettoie le cache.
        $this->cleanCache();

        return true;

    }

    /**
     * Méthode pour changer l'état d'un enregistrement.
     *
     * @param $pks   array Un tableau des clés primaires représentantes des enregistrements à modifier.
     * @param $value int   La valeur de l'état de publication.
     *
     * @return bool
     */
    public function publish($pks, $value = 0) {

        // On s'assure d'avoir un tableau.
        $pks = (array)$pks;

        // On récupère le table.
        $table = $this->getTable();

        // On parcourt tous les éléments.
        foreach ($pks as $i => $pk) {

            // On tente de charger la ligne.
            if ($table->load($pk) === false) {
                $this->setError($table->getError());

                return false;
            }

            // On tente de changer l'état de l'enregistrement.
            if (!$table->publish($pks, $value)) {
                $this->setError($table->getError());

                return false;
            }

            // On nettoie le cache.
            $this->cleanCache($pk);
        }

        return true;

    }

    /**
     * Méthode pour ajuster l'ordre d'une ligne.
     *
     * Retourne NULL si l'utilisateur n'a pas les privilèges d'édition sur
     * une des lignes sélectionnées.order
     *
     * @param   integer $pks   La clé primaire.
     * @param   integer $delta Incrément, souvent +1 ou -1
     *
     * @return  mixed  False en cas d'échec
     */
    public function reorder($pks, $delta = 0) {

        $table  = $this->getTable();
        $pks    = (array)$pks;
        $result = true;

        $allowed = true;

        foreach ($pks as $i => $pk) {
            $table->clear();

            if ($table->load($pk)) {

                $where = $this->getReorderConditions($table);

                if (!$table->move($delta, $where)) {
                    $this->setError($table->getError());
                    unset($pks[$i]);
                    $result = false;
                }

            } else {
                $this->setError($table->getError());
                unset($pks[$i]);
                $result = false;
            }
        }

        if ($allowed === false && empty($pks)) {
            $result = null;
        }

        // Clear the component's cache
        if ($result == true) {
            $this->cleanCache();
        }

        return $result;
    }

    /**
     * Méthode pour nettoyer le cache.
     *
     * @param string $id Un identifiant de cache optionnel.
     *
     * @return bool
     */
    public function cleanCache($id = null) {

        $container = $this->getContainer();

        if ($container->has('cache')) {
            $cache = $container->get('cache');

            // Si on a fourni une clé, on ne supprime que l'élément mis en cache.
            if (isset($id)) {
                return $cache->delete($this->getStoreId($id), $this->getCacheGroup());
            } else { // Sinon, on supprime le groupe entier.
                return $cache->clean($this->getCacheGroup(), 'group');
            }
        }

        return true;

    }

    /**
     * Méthode pour modifier le formulaire avant la liaison avec les données.
     *
     * @param Form  $form Le formulaire.
     * @param array $data Les données liées au formulaire
     *
     * @return Form
     */
    protected function preprocessForm(Form $form, $data = array()) {

        return $form;
    }

    /**
     * Prépare et nettoie les données du Table avant son enregistrement.
     *
     * @param   Table $table Une référence à un objet Table.
     *
     * @return  Table
     */
    protected function preprocessTable(Table $table) {

        // Les classes dérivées pourront l'implémenter si besoin.

        return $table;
    }

    /**
     * Prépare le Table avant sa duplication.
     * On l'utilise pour changer certain champs avant son insertion en BDD.
     *
     * @param Table $table Une référence à un objet Table.
     */
    protected function prepareDuplicatedTable(Table &$table) {

        // Les classes dérivées pourront l'implémenter si besoin.

    }

    /**
     * Est appelée après la duplication d'un Table.
     *
     * @param Table $table  Une référence à un objet Table.
     * @param int   $old_pk L'ancien identifint.
     */
    protected function afterDuplicatedTable(Table &$table, $old_pk) {

        // Les classes dérivées pourront l'implémenter si besoin.

    }

    /**
     * Prépare le Table avant de lui lier des données.
     *
     * @param Table $table Une référence à un objet Table.
     * @param array $data  Les données à lui lier.
     * @param bool  $isNew True si c'est un nouvel enregistrement, false sinon.
     */
    protected function beforeTableBinding(Table &$table, &$data, $isNew = false) {

        // Les classes dérivées pourront l'implémenter si besoin.

    }

    protected function loadFormData($options = array()) {

        // Je tente les charger les données depuis la session.
        if ($this->app instanceof \EtdSolutions\Application\Web) {
            $data = $this->app->getUserStateFromRequest($this->context . '.edit.data', 'etdform', null, 'array');
        } else {
            $data = [];
        }

        // Si on a pas de données, on charge celle de l'élément si on a est en édition.
        if (empty($data) && $this->get($this->context . '.id')) {
            $data = $this->getItem();
        } elseif (!isset($data)) {
            $data = array();
        }

        return $data;

    }

    /**
     * Méthode pour définir automatiquement l'état du modèle.
     */
    protected function populateState() {

        // Load the object state.
        $id = $this->app->input->get('id', 0, 'int');
        $this->set($this->context . '.id', $id);
    }

    /**
     * Définit la WHERE pour réordonner les lignes.
     *
     * @param array $conditions Un tableau de conditions à ajouter pour effectuer l'ordre.
     * @param Table $table      Une instance Table.
     */
    public function setReorderConditions($conditions = null, $table = null) {

        if (!isset($conditions)) {
            $conditions = array();
        }

        $this->reorderConditions = $conditions;
    }

    /**
     * Donne la clause WHERE pour réordonner les lignes.
     * Cela permet de s'assurer que la ligne sera déplacer relativement à une ligne qui correspondra à cette clause.
     *
     * @param   Table $table Une instance Table.
     *
     * @return  array  Un tableau de conditions à ajouter pour effectuer l'ordre.
     */
    protected function getReorderConditions($table) {

        if (!isset($this->reorderConditions)) {
            $this->setReorderConditions(null, $table);
        }

        return $this->reorderConditions;
    }

    protected function getStoreId($id = '') {
        return $id;
    }

    protected function getCacheGroup() {
        return $this->cachegroup;
    }

}