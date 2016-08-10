<?php

class Termin {

    public $titel = "";
    public $termin_data = NULL;
    private $start = 0;
    private $ende = 0;
    private $spalten_gesamt = 1;
    private $spalte = -1;

    public function __construct($termin_data) {


        $this->termin_data = $termin_data;
        $this->titel = $termin_data["summary"];
        if (empty($termin_data["ganztagig"])) {
            $this->start = (int) $termin_data["start"];
            $this->ende = $this->start + (int) $termin_data["duration"];
        }
    }

    public function erhoehe_spalten_gesamt($anzahl) {
        if (is_null($anzahl) || !is_int($anzahl))
            return false;

        if ($anzahl > $this->spalten_gesamt)
            $this->spalten_gesamt = $anzahl;
    }

    public function hole_spalten_gesamt() {
        return $this->spalten_gesamt;
    }

    public function hole_spalte() {
        return $this->spalte;
    }

    public function setze_spalte($spalte) {
        if (is_null($spalte) || !is_int($spalte))
            return false;

        $this->spalte = $spalte;
    }

    public function ist_gesetzt() {
        return $this->spalte === -1;
    }

    public function hole_start() {
        return $this->start;
    }

    public function hole_ende() {
        return $this->ende;
    }

    public function hole_laenge() {
        return $this->ende - $this->start;
    }

    public function findet_statt($zeit = 0) {
        if (is_null($zeit) || !is_int($zeit))
            return false;

        if ($this->hole_start() < $zeit && $zeit < $this->hole_ende()) {
            return true;
        } else {
            return false;
        }
    }

    public function kollidiert_mit($anfang = 0, $ende = 0) {
        
        
        if (is_null($anfang) || !is_int($anfang))
            return false;
        if (is_null($ende) || !is_int($ende))
            return false;
        
        for ($m = min($anfang, $this->hole_start()); $m < max($ende, $this->hole_ende()); $m++) {

            if (($anfang <= $m && $ende >= $m)) {
                if ($this->findet_statt($m)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function speichern() {
        $this->termin_data["spalten_gesamt"] = $this->spalten_gesamt;
        $this->termin_data["spalte"] = $this->spalte;
    }

}

class Tag {

    private $termine = NULL;

    public function __construct($termine = NULL) {
        if (is_null($termine) || !is_array($termine))
            return -1;

        $this->termine = array();
        foreach ($termine as $termin_data) {
            $termin = new Termin($termin_data);
            $this->termine[] = $termin;
        }
    }

    public function markiere_termin_spalten() {

        for ($m = 0; $m < $this->tag_laenge(); $m++) {

            // Welche Events finden gerade statt?
            $aktuelle_termine = array();
            for ($e = 0; $e < count($this->termine); $e++) {

                if ($this->termine[$e]->findet_statt($m)) {
                    $aktuelle_termine[] = $this->termine[$e];
                }
            }

            // Markiere aktuelle Events
            for ($ae = 0; $ae < count($aktuelle_termine); $ae++) {
                $aktuelle_termine[$ae]->erhoehe_spalten_gesamt(count($aktuelle_termine));
            }
        }
    }

    public function markiere_termin_indizes() {

        for ($e = 0; $e < count($this->termine); $e++) {
            $aktueller_termin = $this->termine[$e];

            $spalte_gefunden = false;
            for ($spalte = 0; $spalte < $aktueller_termin->hole_spalten_gesamt() && !$spalte_gefunden; $spalte++) {

                $nachbarn = $this->termine;
                unset($nachbarn[$e]);
                
                $termine = $this->filtere_termine($nachbarn, $aktueller_termin->hole_start(), $aktueller_termin->hole_ende(), $spalte);

                if (count($termine) == 0) {
                    $spalte_gefunden = true;
                    $aktueller_termin->setze_spalte($spalte);
                }
            }
        }

        for ($e = 0; $e < count($this->termine); $e++) {
            if ($this->termine[$e]->hole_spalte() === -1) {
                $this->termine[$e]->setze_spalte($this->termine[$e]->hole_spalten_gesamt() - 1);
            }
        }
    }

    public function filtere_termine($termine = NULL, $anfang = 0, $ende = 0, $spalte = 0) {
        $ergebnis = array();

        foreach ($termine as $aktueller_termin) {
            
            if ($aktueller_termin->hole_spalte() == $spalte && $aktueller_termin->kollidiert_mit($anfang, $ende)) {                
                $ergebnis[] = $aktueller_termin;
            }
        }
        
        return $ergebnis;
    }

    public function tag_laenge() {
        $tag_laenge = 0;
        foreach ($this->termine as $termin) {
            $tag_laenge = max($tag_laenge, $termin->hole_ende());
        }
        return $tag_laenge;
    }

    public function neue_termine() {

        $termine = array();
        foreach ($this->termine as $termin) {

            $termin->speichern();

            $spacing = 1;
            $width = 90 / $termin->hole_spalten_gesamt() - 2 * $spacing;
            $left = 5 + (($width + 2 * $spacing) * $termin->hole_spalte());
            $termin->termin_data["width"] = $width;
            $termin->termin_data["left"] = $left;

            $termine[] = $termin->termin_data;
        }

        return $termine;
    }

}
