<?php
require_once("MysqlManager.php");

class MysqlTasks extends MysqlManager
{

    //Query all the existing categories for `gebiete`
    public function getAllCategoriesGebiete()
    {
        try {
            $log = "  getAllCategoriesGebiete ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            error_log("This is a getAllCategoriesError!");
            $sql = $sql_kategorie = "SELECT * FROM m_thematische_kategorien, m_them_kategorie_freigabe
                        WHERE m_thematische_kategorien.ID_THEMA_KAT = m_them_kategorie_freigabe.ID_THEMA_KAT
                        AND STATUS_KATEGORIE_FREIGABE >=  " . $this->berechtigung . "
                        GROUP BY SORTIERUNG_THEMA_KAT";
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
        file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
        return $this->query($sql);
    }

    //Query all the existing categories for `raster`
    public function getAllCategoriesRaster()
    {
        try {
            $log = "  getAllCategoriesRaster ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            $sql = "select * from m_thematische_kategorien, m_indikatoren, d_raster 
                where m_thematische_kategorien.ID_THEMA_KAT = m_indikatoren.ID_THEMA_KAT 
                and m_indikatoren.ID_INDIKATOR = d_raster.Indikator 
                and d_raster.freigabe_aussen = " . $this->berechtigung . " 
                group by m_thematische_kategorien.id_thema_kat 
                order by m_thematische_kategorien.sortierung_thema_kat";

            file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
            return $this->query($sql);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    //Query all the possible spatial extends for a single indicator and a given modus (raster or gebiete)
    public function getSpatialExtend($modus, $year, $ind)
    {
        try {
            $log = "  getSpatialExtend ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            $sql_gebiete = "SELECT i.ID_INDIKATOR, i.RAUMEBENE_BLD,i.RAUMEBENE_ROR,i.RAUMEBENE_KRS,i.RAUMEBENE_LKS,
                            i.RAUMEBENE_KFS,i.RAUMEBENE_VWG,i.RAUMEBENE_GEM,i.RAUMEBENE_G50,i.RAUMEBENE_STT
                            FROM m_indikatoren i, m_indikator_freigabe f
                            WHERE f.JAHR =" . $year . " AND i.ID_INDIKATOR= '" . $ind . "' 
                            AND f.STATUS_INDIKATOR_FREIGABE =3 
                            Group BY i.ID_INDIKATOR ";

            $sql_raster = "SELECT d_raumgliederung.RAUMGLIEDERUNG FROM d_raster,d_raumgliederung
                            WHERE d_raumgliederung.RAUMGLIEDERUNG = d_raster.RAUMGLIEDERUNG
                            AND d_raster.INDIKATOR = '" . $ind . "' 
                            group by d_raster.raumgliederung ORDER BY d_raumgliederung.SORTIERUNG ASC";

            file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
            if ($modus === "raster") {
                return $this->query($sql_raster);
            } else {
                return $this->query($sql_gebiete);
            }
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    // Get all sorts of names for the spatial structure like Bundesland, Raumordnungsregionen...
    public function getSpatialExtendDictionary()
    {
        try {
            $log = "  getSpatialExtendDictionary ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            $sql = "SELECT Raumgliederung_HTML as name, NAME_EN as name_en, DB_KENNUNG as id, Sortierung as order_id from v_raumgliederung Group By NAME order by Sortierung";
            file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
            return $this->query($sql);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    /*Get all indicator values in a spatial extend and the difference between BRD-AGS and KRS-AGS if set, needed for the dropdown menu
    -> result is a json with all the necessary information inside which are needed to create the map
    */
    public function getAllIndicatorValuesInAGS($year, $ags)
    {
        try {
            $log = "  getAllIndicatorValuesInAGS ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            $length_ags = strlen($ags);
            $sql_bld = "";
            $sql_krs = "";
            $grundakt_query = "";
            $sql_brd = "";
            //create the subquery for brd
            $sql_brd = "IFNULL((SELECT x.INDIKATORWERT FROM m_indikatorwerte_" . $year . " x WHERE x.ID_INDIKATOR = 'Z00AG' AND x.ags='99' AND x.INDIKATORWERT <=" . $year . "),0) as grundakt_year_brd,
                  IFNULL((SELECT y.INDIKATORWERT FROM m_indikatorwerte_" . $year . " y WHERE y.ID_INDIKATOR = 'Z01AG' and y.AGS ='99' AND y.INDIKATORWERT <= " . $year . "),0) as grundakt_month_brd,
                  (select b.INDIKATORWERT from m_indikatorwerte_" . $year . " b where AGS='99' and b.ID_INDIKATOR=i.ID_INDIKATOR GROUP BY b.INDIKATORWERT) as value_brd,
                  INDIKATORWERT-(select b.INDIKATORWERT from m_indikatorwerte_" . $year . " b where AGS='99' and b.ID_INDIKATOR=i.ID_INDIKATOR GROUP BY b.INDIKATORWERT) as diff_brd,";

            if ($length_ags > 2) {
                $ags_bld = substr($ags, 0, 2);
                $sql_bld = "IFNULL((SELECT x.INDIKATORWERT FROM m_indikatorwerte_" . $year . " x WHERE x.ID_INDIKATOR = 'Z00AG' AND x.ags='" . $ags_bld . "' AND x.INDIKATORWERT <=" . $year . "),0) as grundakt_year_bld,
                        IFNULL((SELECT y.INDIKATORWERT FROM m_indikatorwerte_" . $year . " y WHERE y.ID_INDIKATOR = 'Z01AG' and y.AGS ='" . $ags_bld . "' AND y.INDIKATORWERT <= " . $year . "),0) as grundakt_month_bld,
                        (select l.INDIKATORWERT from m_indikatorwerte_" . $year . " l where AGS='" . $ags_bld . "' and l.ID_INDIKATOR=i.ID_INDIKATOR GROUP BY l.INDIKATORWERT) as value_bld,
                        INDIKATORWERT-(select k.INDIKATORWERT from m_indikatorwerte_" . $year . " k where AGS='" . $ags_bld . "' and k.ID_INDIKATOR=i.ID_INDIKATOR GROUP BY k.INDIKATORWERT) as diff_bld,";
            }

            if ($length_ags > 5) {
                $ags_krs = substr($ags, 0, 5);
                $sql_krs = "IFNULL((SELECT x.INDIKATORWERT FROM m_indikatorwerte_" . $year . " x WHERE x.ID_INDIKATOR = 'Z00AG' AND x.ags='" . $ags_krs . "' AND x.INDIKATORWERT <=" . $year . "),0) as grundakt_year_krs,
                        IFNULL((SELECT y.INDIKATORWERT FROM m_indikatorwerte_" . $year . " y WHERE y.ID_INDIKATOR = 'Z01AG' and y.AGS ='" . $ags_krs . "' AND y.INDIKATORWERT <= " . $year . "),0) as grundakt_month_krs,
                        (select l.INDIKATORWERT from m_indikatorwerte_" . $year . " l where AGS='" . $ags_krs . "' and l.ID_INDIKATOR=i.ID_INDIKATOR GROUP BY l.INDIKATORWERT) as value_krs,
                        INDIKATORWERT-(select k.INDIKATORWERT from m_indikatorwerte_" . $year . " k where AGS='" . $ags_krs . "' and k.ID_INDIKATOR=i.ID_INDIKATOR GROUP BY k.INDIKATORWERT) as diff_krs,";
            }

            $sql = "Select i.ID_INDIKATOR as id, i.INDIKATORWERT AS value,
                IFNULL((SELECT x.INDIKATORWERT FROM m_indikatorwerte_" . $year . " x WHERE x.ID_INDIKATOR = 'Z00AG' AND x.ags=i.AGS AND x.INDIKATORWERT <=" . $year . "),0) as grundakt_year,
                IFNULL((SELECT y.INDIKATORWERT FROM m_indikatorwerte_" . $year . " y WHERE y.ID_INDIKATOR = 'Z01AG' and y.AGS =i.AGS AND y.INDIKATORWERT <= " . $year . "),0) as grundakt_month,
                z.Einheit as einheit,
                " . $sql_brd . $sql_krs . $sql_bld . "
                (select j.ID_THEMA_KAT from m_thematische_kategorien j where z.ID_THEMA_KAT=j.ID_THEMA_KAT) as category
                from m_indikatorwerte_" . $year . " i, m_indikatoren z, m_indikator_freigabe f
                where i.ID_INDIKATOR=z.ID_INDIKATOR 
                and i.AGS = '" . $ags . "'
                And z.ID_INDIKATOR = f.ID_INDIKATOR
                AND f.STATUS_INDIKATOR_FREIGABE = '" . $this->berechtigung . "' 
                group by i.ID_INDIKATOR";
            file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
            return $this->query($sql);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    /*Get all possible Indicators in a Indicator Category for 'gebiete' or 'raster'-------*/
    public function getAllIndicatorsByCategoryGebiete($kat, $modus)
    {
        try {
            $log = "  getAllIndicatorsByCategoryGebiete ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            $sql = "SELECT m.*,
                IFNULL((SELECT FARBWERT_MIN from m_zeichenvorschrift where ID_INDIKATOR = m.ID_INDIKATOR),'FFCC99') as FARBWERT_MIN,
                IFNULL((SELECT FARBWERT_MAX from m_zeichenvorschrift where ID_INDIKATOR = m.ID_INDIKATOR),'66CC99') as FARBWERT_MAX
                FROM m_indikatoren m, m_indikator_freigabe f
                WHERE m.ID_THEMA_KAT =  '" . $kat . "'
                AND m.ID_INDIKATOR = f.ID_INDIKATOR
                AND f.STATUS_INDIKATOR_FREIGABE =  '" . $this->berechtigung . "'
                GROUP BY m.INDIKATOR_NAME_KURZ
                ORDER BY  m.MARKIERUNG DESC, m.SORTIERUNG ASC";

            if ($modus === "raster") {
                $sql = "SELECT m.*,IFNULL(z.FARBWERT_MIN,'FFCC99') as FARBWERT_MIN,IFNULL(z.FARBWERT_MAX,'66CC99') as FARBWERT_MAX 
                FROM m_indikatoren m, d_raster r, m_zeichenvorschrift z
                WHERE m.ID_THEMA_KAT =  '" . $kat . "'
                AND m.ID_INDIKATOR = r.INDIKATOR
                AND r.Freigabe_AUSSEN >=  '" . $this->berechtigung . "'
                And m.ID_INDIKATOR = z.ID_INDIKATOR
                GROUP BY m.INDIKATOR_NAME_KURZ
                ORDER BY  m.MARKIERUNG DESC, m.SORTIERUNG ASC";
            }
            file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
            return $this->query($sql);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    // get all the indicator values and specific informations like `Grundaktualit??t` for a special Indicator selected by year, id and it??s ags
    public function getIndicatorValuesByAGS($year, $indikator_id, $ags)
    {
        try {
            $sql = "SELECT i.INDIKATORWERT AS value, i.ID_INDIKATOR as ind, z.EINHEIT as einheit,i.FEHLERCODE as fc, i.HINWEISCODE as hc, i.AGS as ags, z.RUNDUNG_NACHKOMMASTELLEN as rundung,
                                IFNULL((SELECT x.INDIKATORWERT FROM m_indikatorwerte_" . $year . " x WHERE x.ID_INDIKATOR = 'Z00AG' AND x.ags=i.AGS AND x.INDIKATORWERT <=" . $year . "),0) as grundakt_year,
                                IFNULL((SELECT y.INDIKATORWERT FROM m_indikatorwerte_" . $year . " y WHERE y.ID_INDIKATOR = 'Z01AG' and y.AGS =i.AGS AND y.INDIKATORWERT <= " . $year . "),0) as grundakt_month,
                                z.MITTLERE_AKTUALITAET_IGNORE as grundakt_state,
                                z.INDIKATOR_NAME as name,
                                IFNULL((SELECT FARBWERT_MAX FROM m_zeichenvorschrift WHERE ID_INDIKATOR='" . $indikator_id . "'),'FFCC99') as color_max,
                                IFNULL((SELECT FARBWERT_MIN FROM m_zeichenvorschrift WHERE ID_INDIKATOR='" . $indikator_id . "'),'66CC99') as color_min
                                FROM m_indikatorwerte_" . $year . " i, m_indikator_freigabe f, m_indikatoren z
                                Where f.ID_INDIKATOR = i.ID_INDIKATOR AND f.ID_INDIKATOR =  '" . $indikator_id . "'
                                AND f.STATUS_INDIKATOR_FREIGABE = " . $this->berechtigung . "
                                And z.ID_INDIKATOR = f.ID_INDIKATOR
                                AND i.AGS = '" . $ags . "'
                                Group By i.AGS";

            return $this->query($sql);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    /* get only the single AGS-value for a given Indicator*/
    public function getIndicatorValueByAGS($ind, $ags, $year)
    {
        try {
            $sql = "SELECT m.INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " m
            INNER JOIN m_fehlercodes f ON IFNULL(m.FEHLERCODE,0) = f.FEHLERCODE
            WHERE m.ags = '" . $ags . "' AND m.ID_INDIKATOR = '" . $ind . "' Group by m.Indikatorwert";
            $rs = $this->query($sql);
            return $rs[0]->value;
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    /* function to get all values in a given spatial extend for instance saxony
        - $ags is a example AGS inside the ags array, f.eg. the first value to calculate the digit length
    */
    public function getIndicatorValuesInSpatialExtend($year, $indikator_id, $ags, $ags_user_array)
    {
        try {
            $ags_extend = "";
            if (count($ags_user_array) > 0) {
                $ags_extend .= " AND i.AGS REGEXP '";
                foreach ($ags_user_array as $value) {
                    $ags_extend .= $value . "|";
                }
                $ags_extend = substr($ags_extend, 0, -1);
                $ags_extend = $ags_extend . "'";
            }

            //build the sql query
            $sql = "SELECT i.INDIKATORWERT AS value, i.ID_INDIKATOR as ind, z.EINHEIT as einheit,i.FEHLERCODE as fc, i.HINWEISCODE as hc, i.AGS as ags, z.RUNDUNG_NACHKOMMASTELLEN as rundung,
                                IFNULL((SELECT x.INDIKATORWERT FROM m_indikatorwerte_" . $year . " x WHERE x.ID_INDIKATOR = 'Z00AG' AND x.ags=i.AGS GROUP BY x.INDIKATORWERT),0) as grundakt_year,
                                IFNULL((SELECT y.INDIKATORWERT FROM m_indikatorwerte_" . $year . " y WHERE y.ID_INDIKATOR = 'Z01AG' and y.AGS =i.AGS GROUP BY y.INDIKATORWERT),0) as grundakt_month,
                                z.MITTLERE_AKTUALITAET_IGNORE as grundakt_state,
                                z.INDIKATOR_NAME_KURZ as name,
                                IFNULL((SELECT FARBWERT_MAX FROM m_zeichenvorschrift WHERE ID_INDIKATOR='" . $indikator_id . "'),'FFCC99') as color_max,
                                IFNULL((SELECT FARBWERT_MIN FROM m_zeichenvorschrift WHERE ID_INDIKATOR='" . $indikator_id . "'),'66CC99') as color_min
                                FROM m_indikatorwerte_" . $year . " i, m_indikator_freigabe f, m_indikatoren z
                                Where f.ID_INDIKATOR = i.ID_INDIKATOR AND f.ID_INDIKATOR =  '" . $indikator_id . "'
                                AND f.STATUS_INDIKATOR_FREIGABE = " . $this->berechtigung . "
                                And z.ID_INDIKATOR = f.ID_INDIKATOR
                                And LENGTH(i.AGS) = " . (strlen($ags))
                . $ags_extend . "
                                and not i.AGS='99'
                                Group by i.AGS";

            if ($indikator_id === 'Z00AG') {
                $sql = "SELECT i.INDIKATORWERT AS value, i.ID_INDIKATOR as ind, i.ID_INDIKATORWERT as einheit,i.FEHLERCODE as fc, i.HINWEISCODE as hc, i.AGS as ags FROM m_indikatorwerte_" . $year . " i 
            Where i.ID_INDIKATOR = 'Z00AG' And LENGTH(i.AGS) = " . (strlen($ags)) . $ags_extend;
            }
            return $this->query($sql);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    // get all the possible time shifts for a given indicator, it is also possible to exclude specific years
    public function getIndicatorPossibleTimeArray($ind, $modus, $exclude_year)
    {
        try {
            $times = array();
            if ($modus === 'gebiete') {
                $ex_q = '';
                foreach ($exclude_year as $value) {
                    $ex_q .= " And NOT JAHR =" . $value;
                }
                $query = "SELECT JAHR FROM m_indikator_freigabe
                      WHERE STATUS_INDIKATOR_FREIGABE >= '" . $this->berechtigung . "'
                      AND ID_INDIKATOR = '" . $ind . "'" . $ex_q . "
                      Order by JAHR DESC";
            } else {
                if ($exclude_year) {
                    $query = "SELECT JAHR FROM d_raster
                            WHERE d_raster.freigabe_aussen >= '" . $this->berechtigung . "'
                            AND INDIKATOR = '" . $ind . "' AND NOT JAHR=" . $exclude_year . "
                            group by JAHR
                            Order by JAHR DESC";
                } else {
                    $query = "SELECT JAHR FROM d_raster
                            WHERE d_raster.freigabe_aussen >= '" . $this->berechtigung . "'
                            AND INDIKATOR = '" . $ind . "'
                            group by JAHR
                            Order by JAHR DESC";
                }
            }
            $ergObject = $this->query($query);

            foreach ($ergObject as $row) {
                array_push($times, array("time" => $row->JAHR));
            }
            //needs to be sorted to make sure every column takes the correct place
            usort($times, function ($item1, $item2) {
                return $item1['time'] <=> $item2['time'];
            });
            return $times;
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    //check if a indicator is avaliable for the given modus (raster, gebiete) needed for the switch between raster and svg maps
    public function checkIndicatorAvability($indikator, $modus)
    {
        try {
            $query = "SELECT ID_INDIKATOR, Jahr FROM m_indikator_freigabe WHERE ID_INDIKATOR = '" . $indikator . "' AND STATUS_INDIKATOR_FREIGABE ='" . $this->berechtigung . "' Group by Jahr";
            if ($modus === 'raster') {
                $query = "SELECT Indikator,Jahr FROM d_raster WHERE INDIKATOR = '" . $indikator . "' AND FREIGABE_AUSSEN ='" . $this->berechtigung . "' Group by JAHR";
            }
            $rs = $this->query($query);
            if (count($rs) > 0) {
                return true;
            } else {
                return false;
            }
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    public function getIndicatorGrundaktualitaet($ags, $year)
    {
        try {
            $sql_year = "SELECT INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " WHERE ID_INDIKATOR = 'Z00AG' and AGS ='" . $ags . "' AND INDIKATORWERT <= " . $year . " ";
            $sql_month = "SELECT INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " WHERE ID_INDIKATOR = 'Z01AG' and AGS ='" . $ags . "' AND INDIKATORWERT <= " . $year . " ";

            $rs_akt_year = $this->query($sql_year);
            $rs_akt_mon = $this->query($sql_month);
            if ($year >= date("Y")) {
                return "1/" . $year;
            } else if (count($rs_akt_year) == 0) {
                return "nicht verf??gbar";
            } else {
                return $rs_akt_year[0]->value . "/" . $rs_akt_mon[0]->value;
            }
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    public function getIndicatorValueForBRD($indicator_id, $year)
    {
        try {
            $sql = "Select INDIKATORWERT as value FROM m_indikatorwerte_" . $year . " where ID_INDIKATOR ='" . $indicator_id . "' AND AGS = '99'";
            $rs = $this->query($sql);
            return $rs[0]->value;
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    public function getIndicatorColors($ind)
    {
        try {
            $sql = "SELECT IFNULL(FARBWERT_MAX,'FFCC99') as max,IFNULL(FARBWERT_MIN,'66CC99') as min FROM m_zeichenvorschrift WHERE ID_INDIKATOR='" . $ind . "'";

            $rs = $this->query($sql);
            return $rs[0];
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    public function getGrundaktState($ind)
    {
        try {
            $sql = "SELECT MITTLERE_AKTUALITAET_IGNORE as value FROM m_indikatoren where ID_INDIKATOR = '" . $ind . "'";
            $rs = $this->query($sql);
            return intval($rs[0]->value);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }

    public function getPostGreYear($year)
    {
        try {
            $log = "  getPostGreYear ";
            file_put_contents('log.txt', "MySQL: " . $log, FILE_APPEND);
            $sql = "select PostGIS_Tabelle_Jahr from v_geometrie_jahr_viewer_postgis where Jahr_im_Viewer =" . $year;
            $rs = $this->query($sql);
            file_put_contents('log.txt', "DONE! " . PHP_EOL, FILE_APPEND);
            return intval($rs[0]->PostGIS_Tabelle_Jahr);
        } catch (Error $e) {
            $trace = $e->getTrace();
            echo $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . ' called from ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'];
        }
    }
}