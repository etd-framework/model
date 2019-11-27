<?php
/**
 * Part of the ETD Framework Model Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Model;

class CacheHelper {

    const SEPARATOR = "_";

    public static function getStoreId($id = '', $group = null) {

        $id = self::serializeId($id);

        return (isset($group) ? $group . self::SEPARATOR : "") . md5($id);
    }

    public static function serializeId($id) {

        if (is_string($id)) {
            return $id;
        }

        $ret = "";

        if (empty($id)) {
            return $ret;
        }

        foreach ((array) $id as $k => $v) {
            $ret .= self::SEPARATOR . $k . "=";
            if (is_array($v)) {
                $ret .= implode(",", $v);
            } elseif (is_object($v)) {
                $ret .= implode(",", get_object_vars($v));
                error_log($ret);
            } elseif (is_null($v)) {
                $ret .= "null";
            } elseif (is_bool($v)) {
                $ret .= ($v ? "true" : "false");
            } else {
                $ret .= (string) $v;
            }
        }
        return $ret;
    }

}