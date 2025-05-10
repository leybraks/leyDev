<?php
// CLASS/Usuario.php

/**
 * Representa los datos principales de la cuenta de un usuario.
 * Contiene información para login, estado de la cuenta, rol, etc.
 * Se mapea principalmente a la tabla 'users'.
 */
class Usuario {

    // --- Propiedades Privadas (Datos de la Cuenta) ---
    private $id = null;
    private $usuario = null; // Corresponde a 'username' en la BD
    private $correo = null;  // Corresponde a 'email' en la BD
    private $clave = null;   // Usado SOLO para guardar temporalmente la clave introducida por el usuario
    private $active = null; // Estado activo (0 o 1)
    private $id_eCon = null;// Estado de confirmación email (0 o 1)
    private $perfil_completo = null; // Bandera de perfil completo (0 o 1)
    private $role = null; // Rol del usuario (ej. 'alumno', 'tutor')

    // --- Getters ---
    public function getId() { return $this->id; }
    public function getUsuario() { return $this->usuario; }
    public function getCorreo() { return $this->correo; }
    public function getClave() { return $this->clave; } // Devuelve la clave en texto plano (si se estableció)
    public function isActive() { return $this->active == 1; }
    public function isEmailConfirmed() { return $this->id_eCon == 1; }
    public function isPerfilCompleto() { return $this->perfil_completo == 1; }
    public function getRole() { return $this->role; }

    // --- Setters ---
    public function setId($id) { $this->id = $id ? (int)$id : null; }
    public function setUsuario($usuario) { $this->usuario = $usuario ? trim($usuario) : null; }
    public function setCorreo($correo) { $this->correo = $correo ? trim($correo) : null; }
    public function setClave($clave) { $this->clave = $clave; } // Guarda la clave (probablemente texto plano)
    public function setActive($active) { $this->active = $active ? (int)$active : 0; }
    public function setIdEcon($id_eCon) { $this->id_eCon = $id_eCon ? (int)$id_eCon : 0; }
    public function setPerfilCompleto($perfil_completo) { $this->perfil_completo = $perfil_completo ? 1 : 0; }
    public function setRole($role) { $this->role = $role ? trim($role) : null; }


    /**
     * Crea una instancia de Usuario a partir de un array de datos (normalmente de la BD).
     * Mapea nombres de columna de la BD (como 'username') a propiedades del objeto.
     * NO asigna el hash de la contraseña a la propiedad 'clave'.
     *
     * @param array $data Array asociativo con datos del usuario desde la BD.
     * @return self Una nueva instancia de Usuario poblada con los datos.
     */
    public static function fromArray(array $data) {
         $user = new self();
         $user->setId($data['id'] ?? null);
         $user->setUsuario($data['username'] ?? null); // Mapea 'username' a $usuario
         $user->setCorreo($data['email'] ?? null);
         $user->setActive($data['active'] ?? 0);
         $user->setIdEcon($data['id_eCon'] ?? 0);
         $user->setPerfilCompleto($data['perfil_completo'] ?? 0);
         $user->setRole($data['role'] ?? null);
         // No asignamos $data['password_hash'] a $this->clave
         return $user;
    }

} // <--- Fin de la clase quitado como pediste
?>