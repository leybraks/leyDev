<?php
// CLASS/Persona.php

/**
 * Guarda la información personal de un usuario.
 * Se corresponde con los datos de la tabla 'user_profiles'.
 * Permite manejar fácilmente los detalles del perfil.
 */
class Persona {

    // --- Propiedades Privadas (Datos del Perfil) ---
    private $user_id = null;
    private $first_name = null;
    private $paternal_last_name = null;
    private $maternal_last_name = null;
    private $birth_date = null;
    private $phone = null;
    private $address = null;
    private $city = null;
    private $country = null;
    private $avatar_url = null;
    private $gender = null;

    // --- Getters (Para obtener los valores) ---

    public function getUserId() { return $this->user_id; }
    public function getFirstName() { return $this->first_name; }
    public function getPaternalLastName() { return $this->paternal_last_name; }
    public function getMaternalLastName() { return $this->maternal_last_name; }
    public function getBirthDate() { return $this->birth_date; }
    public function getPhone() { return $this->phone; }
    public function getAddress() { return $this->address; }
    public function getCity() { return $this->city; }
    public function getCountry() { return $this->country; }
    public function getAvatarUrl() { return $this->avatar_url; }
    public function getGender() { return $this->gender; }

    /**
     * Devuelve el nombre completo concatenado.
     * @return string|null Nombre completo o null si no hay partes.
     */
    public function getFullName() {
        $parts = [];
        if (!empty($this->first_name)) $parts[] = $this->first_name;
        if (!empty($this->paternal_last_name)) $parts[] = $this->paternal_last_name;
        if (!empty($this->maternal_last_name)) $parts[] = $this->maternal_last_name;
        return !empty($parts) ? implode(' ', $parts) : null;
    }

    // --- Setters (Para establecer los valores) ---
    public function setUserId($userId) { $this->user_id = $userId ? (int)$userId : null; }
    public function setFirstName($firstName) { $this->first_name = $firstName ? trim($firstName) : null; }
    public function setPaternalLastName($lastName) { $this->paternal_last_name = $lastName ? trim($lastName) : null; }
    public function setMaternalLastName($lastName) { $this->maternal_last_name = $lastName ? trim($lastName) : null; }
    public function setBirthDate($birthDate) { $this->birth_date = $birthDate ? trim($birthDate) : null; }
    public function setPhone($phone) { $this->phone = $phone ? trim($phone) : null; }
    public function setAddress($address) { $this->address = $address ? trim($address) : null; }
    public function setCity($city) { $this->city = $city ? trim($city) : null; }
    public function setCountry($country) { $this->country = $country ? trim($country) : null; }
    public function setAvatarUrl($avatarUrl) { $this->avatar_url = $avatarUrl ? trim($avatarUrl) : null; }
    public function setGender($gender) { $this->gender = $gender ? trim($gender) : null; }


    /**
     * Carga datos desde un array (como $_POST) a las propiedades del objeto.
     * Es flexible con los nombres de las claves del array (acepta inglés/español comunes).
     * @param array $data El array con los datos a cargar.
     */
    public function fillFromArray(array $data) {
        $this->setUserId($data['user_id'] ?? $this->user_id);
        $this->setFirstName($data['first_name'] ?? $data['nombre'] ?? null);
        $this->setPaternalLastName($data['paternal_last_name'] ?? $data['apePaterno'] ?? $data['ape_paterno'] ?? null);
        $this->setMaternalLastName($data['maternal_last_name'] ?? $data['apeMaterno'] ?? $data['ape_materno'] ?? null);
        $this->setBirthDate($data['birth_date'] ?? $data['fecNac'] ?? null);
        $this->setPhone($data['phone'] ?? null);
        $this->setAddress($data['address'] ?? null);
        $this->setCity($data['city'] ?? null);
        $this->setCountry($data['country'] ?? null);
        $this->setAvatarUrl($data['avatar_url'] ?? null);
        $this->setGender($data['gender'] ?? null);
    }

     /**
      * Prepara un array con los datos del perfil listos para la base de datos.
      * Las claves de este array deben coincidir con las columnas de la tabla `user_profiles`.
      * @return array Datos del perfil para guardar/actualizar.
      */
     public function getProfileDataForDb() {
         return [
             'first_name' => $this->first_name,
             'paternal_last_name' => $this->paternal_last_name,
             'maternal_last_name' => $this->maternal_last_name,
             'birth_date' => $this->birth_date,
             'phone' => $this->phone,
             'address' => $this->address,
             'city' => $this->city,
             'country' => $this->country,
             'avatar_url' => $this->avatar_url,
             'gender' => $this->gender,
         ];
     }
} 
?>