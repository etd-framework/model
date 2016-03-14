<?php
/**
 * Part of the ETD Framework Model Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Model;

use EtdSolutions\EtdInterfaces\Helper\EmailHelper;
use EtdSolutions\Table\Table;
use EtdSolutions\Table\UserTable;
use EtdSolutions\User\UserHelper;
use Joomla\Application\AbstractApplication;
use Joomla\Database\DatabaseDriver;
use Joomla\Date\Date;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Modèle pour gérer un utilisateur.
 */
class UserModel extends ItemModel {

    /**
     * @var UserHelper La classe d'aide pour les opérations effectués sur les utilisateurs.
     */
    protected $helper;

    protected $bypass_group_check = false;

    public function __construct(AbstractApplication $app, DatabaseDriver $db, Registry $state = null, $ignore_request = false) {

        parent::__construct($app, $db, $state, $ignore_request);
        
        $this->helper = new UserHelper($db);
    }

    public function getTable($name = null) {

        return new UserTable($this->db);
    }

    /**
     * Retourne les groupes utilisateurs.
     *
     * @return array Un tableau des groupes utilisateurs.
     *
     * @note proxy vers UserHelper::getUserGroups()
     */
    public function getUserGroups() {
        return $this->helper->getUserGroups();
    }

    /**
     * Retour les groupes auxquels appartient un utilisateur.
     *
     * @param int $id L'identifiant de l'utilisateur.
     *
     * @return array Un tableau d'idenfitiant des groupes auxquels appartient l'utilisateur.
     */
    public function getGroupsByUser($id = null) {

        if (empty($id)) {
            $id = $this->get($this->context.'.id');
        }

        if (empty($id)) {
            return [
                $this->getContainer()->get('config')->get('default_user_groups', 3)
            ];
        }

        return $this->helper->getGroupsByUser($id);
    }

    /**
     * Méthode pour changer l'état d'un enregistrement.
     *
     * @param $pks   array Un tableau des clés primaires représentantes des enregistrements à modifier.
     * @param $value int   La valeur de l'état de publication.
     *
     * @return bool
     */
    public function block($pks, $value = 1) {

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
            if (!$table->block($pks, $value)) {
                $this->setError($table->getError());

                return false;
            }
        }

        // On nettoie le cache.
        $this->cleanCache();

        return true;

    }

    /**
     * Prépare le Table avant de lui lier des données.
     *
     * @param Table $table Une référence à un objet Table.
     * @param array $data  Les données à lui lier.
     * @param bool  $isNew True si c'est un nouvel enregistrement, false sinon.
     */
    protected function beforeTableBinding(Table &$table, &$data, $isNew = false) {

        // On appel le parent.
        parent::beforeTableBinding($table, $data, $isNew);

        // On construit le nom complet depuis le prénom et le nom.
        if (isset($data['profile']) && isset($data['profile']['firstName']) && isset($data['profile']['lastName'])) {
            $data['name'] = $data['profile']['firstName'] ." " . $data['profile']['lastName'];
        }

        // Est-ce un nouvel utilisateur ou non.
        if (empty($table->id)) {

            // Si le mot de passe est vide, on crée un mot de passe crypté.
            if (empty($data['password'])) {
                $data['password']  = $this->helper->genRandomPassword();
                $data['password2'] = $data['password'];
            }

            $table->password_clear = ArrayHelper::getValue($data, 'password', '', 'string');

            $data['password'] = $this->helper->cryptPassword($data['password']);

            // Set the registration timestamp
            $table->registerDate = (new Date())->format($this->db->getDateFormat());

        } else { // On met à jour un nouvel utilisateur.

            if (!empty($data['password'])) {

                $table->password_clear = ArrayHelper::getValue($data, 'password', '', 'string');

                $data['password'] = $this->helper->cryptPassword($data['password']);

                // On raz le drapeau de forçage du mot de passe.
                $data['requireReset'] = 0;

            } else {
                $data['password'] = $table->password;
            }
        }

    }

    public function save($data) {

        $result =  parent::save($data);
        $id = (int) $this->get($this->context.'.id');

        $user = $this->getContainer()->get('user')->load();
        if (!$this->bypass_group_check && !$user->authorise('user', 'add')) {
            $data['groups'] = array($this->getContainer()->get('config')->get('default_user_groups', 3));
        }

        if ($result && $id > 0 && isset($data['groups'])) {

            // On s'assure d'avoir un tableau correct.
            $groups = (array)$data['groups'];
            ArrayHelper::toInteger($groups);

            // On supprime toutes les associations existantes si nécessaire.
            if ($this->get($this->context.'.isNew', false) === false) {

                $this->db->setQuery($this->db->getQuery(true)
                    ->delete('#__user_usergroup_map')
                    ->where('user_id = ' . $id))
                    ->execute();

            }

            // On crée les associations dans la base.
            if (!empty($groups)) {

                $tuples = array();
                foreach ($groups as $group) {
                    $tuples[] = $id . "," . $group;
                }

                $this->db->setQuery($this->db->getQuery(true)
                    ->insert('#__user_usergroup_map')
                    ->columns(array(
                        'user_id',
                        'group_id'
                    ))
                    ->values($tuples))
                    ->execute();

            }

        }

        return $result;
    }

    public function delete(&$pks) {

        $result = parent::delete($pks);

        if ($result && !empty($pks)) {

            $db    = $this->db;
            $query = $db->getQuery(true);

            // On supprime l'utilisateur des groupes.
            $query->delete('#__user_usergroup_map')
                ->where('user_id IN (' . implode($pks) . ')');

            $db->setQuery($query)->execute();

            $query->clear()
                  ->delete('#__user_keys')
                  ->where('user_id IN (' . implode(",", $pks) . ')');

            $db->setQuery($query)->execute();

            $query->clear()
                  ->delete('#__user_profiles')
                  ->where('user_id IN (' . implode(",", $pks) . ')');

            $db->setQuery($query)->execute();

        }

        return $result;
    }

    public function reset(&$pks) {

        // On retire l'idenfiant de l'utilisateur en cours.
        $pks = array_diff($pks, array($this->getContainer()->get('user')->id));
        ArrayHelper::toInteger($pks);

        $table  = $this->getTable();
        $helper = new EmailHelper($this->app, $this->getContainer());
        $helper->setEmailTemplate('reset');

        $helper->setData([
            'subject' => 'Votre compte a été réinitialisé',
            'resume'  => 'Votre compte sur la plateforme Interfaces a été réinitialisé.',
            'tags'    => ['password-resets']
        ]);

        foreach( $pks as $pk ) {

            if ($table->load($pk)) {

                $table->password_clear = $this->helper->genRandomPassword();
                $table->password       = $this->helper->cryptPassword($table->password_clear);
                $table->requireReset   = $this->app->get('reset_password.requireReset', false) ? '1' : '0';

                if (!$table->store()) {
                    $this->setError($table->getError());
                    return false;
                }

                $helper->addRecipient($table->email, $table->name);
                $helper->addMergeVars($table->email, [
                    'firstname'      => $table->profile->firstName,
                    'username'       => $table->username,
                    'password_clear' => $table->password_clear
                ]);

            }

        }

        // On envoi les emails.
        $helper->send();

        return true;

    }

}