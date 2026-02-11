<?php

class profile_field_cpf extends profile_field_base {
    public function edit_field_add($mform) {
        $maskjs = "this.value=this.value.replace(/\\D/g,'').replace(/(\\d{3})(\\d)/,'$1.$2').replace(/(\\d{3})(\\d)/,'$1.$2').replace(/(\\d{3})(\\d{1,2})$/,'$1-$2').slice(0,14);";
        $mform->addElement(
            'text',
            $this->inputname,
            format_string($this->field->name),
            'maxlength="14" size="14" id="' . $this->inputname . '" oninput="' . $maskjs . '"'
        );
        $mform->setType($this->inputname, PARAM_TEXT);
    }
    public function edit_validate_field($usernew) {
        $return = array();
        if (isset($usernew->{$this->inputname})) {
            $cpf = $this->normalizecpf($usernew->{$this->inputname});
            if ($cpf === '') {
                return $return;
            }
            if (!$this->exists($cpf, $usernew->id)) {
                $return[$this->inputname] = get_string('cpfexists', 'profilefield_cpf');
            } else if (!$this->validatecpf($cpf)) {
                $return[$this->inputname] = get_string('invalidcpf', 'profilefield_cpf');
            }
        }
        return $return;
    }

    public function edit_save_data_preprocess($data, $datarecord) {
        if ($data === null) {
            return null;
        }
        return $this->normalizecpf($data);
    }

    private function exists($cpf = null, $userid = 0) {
        global $DB;
        // Verifica se um número foi informado.
        if (is_null($cpf) || $cpf === '') {
            return false;
        }

        $sql = "SELECT uid.data FROM {user_info_data} uid
                INNER JOIN {user_info_field} uif ON uid.fieldid = uif.id
                WHERE uif.datatype = 'cpf' AND uid.userid <> :userid";
        $params['userid'] = $userid;
        $cpfs = $DB->get_fieldset_sql($sql, $params);

        foreach ($cpfs as $existingcpf) {
            if ($this->normalizecpf($existingcpf) === $cpf) {
                return false;
            }
        }
        return true;
    }

    private function validatecpf($cpf = null) {
        // Verifica se um numero foi informado.
        if (is_null($cpf)) {
            return false;
        }
        $cpf = $this->normalizecpf($cpf);
        $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);

        // Verifica se o numero de digitos informados eh igual a 11.
        if (strlen($cpf) != 11) {
            return false;
        } else if ($cpf == '00000000000' || $cpf == '11111111111' || $cpf == '22222222222' ||
            $cpf == '33333333333' || $cpf == '44444444444' || $cpf == '55555555555' ||
            $cpf == '66666666666' || $cpf == '77777777777' || $cpf == '88888888888' ||
            $cpf == '99999999999') {
            return false;
        } else {
            // Calcula os digitos verificadores para verificar se o CPF eh valido.
            for ($t = 9; $t < 11; $t++) {

                for ($d = 0, $c = 0; $c < $t; $c++) {
                    $d += $cpf[$c] * (($t + 1) - $c);
                }
                $d = ((10 * $d) % 11) % 10;
                if ($cpf[$c] != $d) {
                    return false;
                }
            }

            return true;
        }
    }

    private function normalizecpf($cpf) {
        if ($cpf === null) {
            return '';
        }
        return preg_replace('/[^0-9]/', '', (string)$cpf);
    }
}
