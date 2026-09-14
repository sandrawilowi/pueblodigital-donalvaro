<?php

declare(strict_types=1);

/**
 * usersService
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 22 jul 2026
 */
class usersService {
    
    use debugTrait;
    private usersModel $usersModel;
    private usersProfileModel $usersProfileModel;
    private passwordService $passwordService;
    private usersTokensModel $userTokensModel;
    private auditLogsService $audit_service;
    private bonusesModel $bonusesModel;
    private usersBonusesModel $usersBonusesModel;
    private accessPinsService $accessPinsService;

    private const PROFILE_IMAGES_PATH = __DIR__ . '/../../../assets/images/usuarios/perfil/';
    
    public function __construct()
    {
        $this->usersModel = new usersModel();
        $this->passwordService = new passwordService();
        $this->userTokensModel = new usersTokensModel();
        $this->audit_service = new auditLogsService();
        $this->usersProfileModel = new usersProfileModel();
        $this->bonusesModel = new bonusesModel();
        $this->usersBonusesModel = new usersBonusesModel();
        $this->accessPinsService = new accessPinsService();
    }

    public function checkRegister(
        string $email,
        string $password,
        string $passwordConfirm,
        string $name,
        string $surname
    ): array {

        $email = strtolower(trim($email));
        $name = trim($name);
        $surname = trim($surname);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                'El correo electrónico no es válido.',
                'INVALID_EMAIL'
            );
        }

        if ($this->usersModel->findByEmail($email) !== null) {
            return serviceResponse::error(
                'Ya existe un usuario registrado con ese correo electrónico.',
                'EMAIL_ALREADY_EXISTS'
            );
        }

        if (empty($name)) {
            return serviceResponse::error(
                'El nombre es obligatorio.',
                'NAME_REQUIRED'
            );
        }

        if (empty($surname)) {
            return serviceResponse::error(
                'Los apellidos son obligatorios.',
                'SURNAME_REQUIRED'
            );
        }
        
        if (empty($email)) {
            return serviceResponse::error(
                'El correo electrónico es obligatorio.',
                'EMAIL_REQUIRED'
            );
        }

        $passwordResponse = $this->passwordService->checkPassword(
            $password,
            $passwordConfirm
        );

        if (!$passwordResponse['success']) {
            return $passwordResponse;
        }

        return serviceResponse::success(
            'Los datos del registro son válidos.',
            'REGISTER_DATA_VALID'
        );
    }
    
    
    public function createUser(
            string $email,
            string $password,
            string $name,
            string $surname,
            int $role
    ): array {

        try {

            $passwordEncrypted = $this->passwordService->encryptPassword($password);

            $this->usersModel->setEmail(strtolower(trim($email)));
            $this->usersModel->setFirstName($name);
            $this->usersModel->setLastName($surname);
            $this->usersModel->setPassword($passwordEncrypted);
            $userId = $this->usersModel->add();            

            if (!$userId) {
                return serviceResponse::error(
                                'No se ha podido crear el usuario.',
                                'USER_CREATE_ERROR'
                        );
            }
            
            // Añadir rol
            $model_role = new usersRolesModel();
            $model_role->setRoleId($role);
            $model_role->setUserId($userId);
            $model_role->add();
            
            // Añadir perfil vacío
            $model_profile = new usersProfileModel();
            $model_profile->setUserId($userId);
            $model_profile->add();

            return serviceResponse::success(
                            'Usuario creado correctamente.',
                            'USER_CREATED',
                            [
                                'user_id' => $userId
                            ]
                    );
        } catch (Throwable $e) {
            //$this->printDebug($e->getMessage(),true);
            return serviceResponse::error(
                            'Se ha producido un error al crear el usuario.',
                            'USER_CREATE_EXCEPTION'
                    );
        }
    }
    
    public function createActivationToken(int $userId): array {
        try {
            /*
             * Token que enviaremos al usuario.
             * 32 bytes generan 64 caracteres hexadecimales.
             */
            $token = bin2hex(random_bytes(32));

            /*
             * En base de datos guardamos solamente el hash.
             */
            $tokenHash = hash('sha256', $token);

            $expiresAt = (new DateTimeImmutable('+24 hours'))
                    ->format('Y-m-d H:i:s');

            $model = new usersTokensModel();

            $model->setUserId($userId);
            $model->setTokenHash($tokenHash);
            $model->setTokenType('ACCOUNT_ACTIVATION');
            $model->setExpiresAt($expiresAt);

            $tokenId = $model->add();

            if (!$tokenId) {
                return serviceResponse::error(
                                'No se ha podido generar el enlace de activación.',
                                'ACTIVATION_TOKEN_CREATE_ERROR'
                        );
            }

            return serviceResponse::success(
                            'Token de activación generado correctamente.',
                            'ACTIVATION_TOKEN_CREATED',
                            [
                                'token' => $token,
                                'expires_at' => $expiresAt
                            ]
                    );
        } catch (Throwable $e) {
            return serviceResponse::error(
                            'Se ha producido un error al generar el enlace de activación.',
                            'ACTIVATION_TOKEN_CREATE_EXCEPTION'
                    );
        }
    }

    public function createSetPasswordToken(int $userId): array {

        return $this->createPasswordToken($userId, 'SET_PASSWORD');
    }

    public function createPasswordRecoveryToken(int $userId): array {

        return $this->createPasswordToken($userId, 'PASSWORD_RECOVERY');
    }

    private function createPasswordToken(int $userId, string $tokenType): array {

        try {

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

            $model = new usersTokensModel();

            $model->setUserId($userId);
            $model->setTokenHash($tokenHash);
            $model->setTokenType($tokenType);
            $model->setExpiresAt($expiresAt);

            $tokenId = $model->add();

            if (!$tokenId) {
                return serviceResponse::error(
                                'No se ha podido generar el enlace para establecer la contraseña.',
                                'PASSWORD_TOKEN_CREATE_ERROR'
                        );
            }

            return serviceResponse::success(
                            'Token para establecer la contraseña generado correctamente.',
                            'PASSWORD_TOKEN_CREATED',
                            [
                                'token' => $token,
                                'expires_at' => $expiresAt
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            'Se ha producido un error al generar el enlace para establecer la contraseña.',
                            'PASSWORD_TOKEN_CREATE_EXCEPTION'
                    );
        }
    }

    public function activateAccount(string $token): array {

        $token = trim($token);

        if ($token === '') {
            return serviceResponse::error(
                            'El enlace de activación no es válido.',
                            'ACTIVATION_TOKEN_EMPTY'
                    );
        }

        try {

            $tokenHash = hash('sha256', $token);

            $tokenData = $this->userTokensModel->findValidToken(
                    $tokenHash,
                    'ACCOUNT_ACTIVATION'
            );

            if ($tokenData === null) {
                return serviceResponse::error(
                                'El enlace de activación no es válido, ha caducado o ya ha sido utilizado.',
                                'ACTIVATION_TOKEN_INVALID'
                        );
            }

            $userId = (int) $tokenData['user_id'];
            $tokenId = (int) $tokenData['id'];
            $oldUser = $this->usersModel->findById($userId);

            /*
             * Activamos la cuenta.
             */
            $this->usersModel->setId($userId);

            $updated = $this->usersModel->updateStatus(usersModel::STATUS_ACTIVE);

            if (!$updated) {
                return serviceResponse::error(
                                'No se ha podido activar la cuenta.',
                                'ACCOUNT_ACTIVATION_ERROR'
                        );
            }

            /*
             * Marcamos el email como verificado.
             */
            if (!$this->usersModel->markEmailVerified()) {
                return serviceResponse::error(
                                'No se ha podido verificar el correo electrónico.',
                                'EMAIL_VERIFICATION_ERROR'
                        );
            }

            /*
             * Marcamos el token como utilizado.
             */
            if (!$this->userTokensModel->markAsUsed($tokenId)) {
                return serviceResponse::error(
                                'No se ha podido completar la activación de la cuenta.',
                                'ACTIVATION_TOKEN_UPDATE_ERROR'
                        );
            }
            
            $newUser = $this->usersModel->findById($userId, false);
            $this->audit_service->update(
                    'wi_users',
                    $userId,
                    $oldUser,
                    [
                        'status' => $newUser['status'],
                        'email_verified_at' => $newUser['email_verified_at']
                    ],
                    __METHOD__,
                    [],
                    'Activación de cuenta'
            );

            return serviceResponse::success(
                            'Su cuenta ya está disponible y puede acceder a la plataforma utilizando su correo electrónico y contraseña.',
                            'ACCOUNT_ACTIVATED'
                    );
        } catch (Throwable $e) {

            $log = new logsModel(
                    'web',
                    'accountActivation.log'
            );

            $log->error(
                    'Error al activar una cuenta',
                    [
                        'exception' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
            );

            return serviceResponse::error(
                            'Se ha producido un error al activar la cuenta.',
                            'ACCOUNT_ACTIVATION_EXCEPTION'
                    );
        }
    }

    /**
     * Bloquea o desbloquea un usuario.
     *
     * @param int $user_id
     * @param bool $blocked
     *
     * @return array
     */
    public function changeBlockStatus(int $user_id, bool $blocked): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if (empty($user_id)) {
                throw new InvalidArgumentException('El identificador del usuario no es válido.');
            }

            $user = $this->usersModel->findById($user_id);

            if (empty($user)) {
                throw new RuntimeException('El usuario no existe.');
            }

            $status = $blocked ? usersModel::STATUS_BLOCKED : usersModel::STATUS_ACTIVE;

            if ((int) $user['status'] === $status) {

                $result['success'] = true;
                $result['message'] = $blocked ? 'El usuario ya está bloqueado.' : 'El usuario ya está desbloqueado.';

                return $result;
            }

            $this->usersModel->setId($user_id);

            if (!$this->usersModel->updateStatus($status)) {
                throw new RuntimeException('No se ha podido actualizar el estado del usuario.');
            }

            $this->audit_service->update(
                    'wi_users',
                    $user_id,
                    $user,
                    [
                        'status' => $status
                    ],
                    __METHOD__,
                    [],
                    $blocked ? 'Bloqueo de usuario' : 'Desbloqueo de usuario'
            );

            $result['success'] = true;
            $result['message'] = $blocked ? 'Usuario bloqueado correctamente.' : 'Usuario desbloqueado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function changeUserStatus(int $user_id, int $status): array {

        $user = $this->usersModel->findById($user_id);

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'El usuario no existe.'
            ];
        }

        $this->usersModel->setId($user_id);
        $this->usersModel->updateStatus($status);

        $this->audit_service->update(
                'wi_users',
                $user_id,
                $user,
                [
                    'status' => $status
                ],
                __METHOD__,
                [],
                $status == usersModel::STATUS_ACTIVE ? 'Activación de usuario' : 'Desactivación de usuario'
        );

        return [
            'success' => true,
            'message' => $status == usersModel::STATUS_ACTIVE ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.'
        ];
    }
    
    public function updateUser(int $user_id, stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if (empty($user_id)) {
                throw new InvalidArgumentException('El identificador del usuario no es válido.');
            }

            $name = trim(strip_tags((string) ($params->name ?? '')));
            $lastname = trim(strip_tags((string) ($params->lastname ?? '')));
            $email = strtolower(trim((string) ($params->email ?? '')));
            $phone = trim(strip_tags((string) ($params->phone ?? '')));
            $address = trim(strip_tags((string) ($params->address ?? '')));
            $city = trim(strip_tags((string) ($params->city ?? '')));
            $province_id = !empty($params->province_id) ? (int) $params->province_id : null;
            $country_id = !empty($params->country_id) ? (int) $params->country_id : null;
            $postal_code = trim(strip_tags((string) ($params->postal_code ?? '')));

            if ($name === '') {
                throw new InvalidArgumentException('El nombre del usuario es obligatorio.');
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El email no es válido.');
            }

            /*
             * Recuperamos todos los datos anteriores del usuario,
             * incluyendo perfil y rol.
             */
            $old_user = $this->usersModel->findEditData($user_id);

            if (empty($old_user)) {
                throw new RuntimeException('El usuario no existe.');
            }

            /*
             * Comprobamos que el email no pertenezca a otro usuario.
             */
            if (strtolower((string) $old_user['email']) !== $email) {

                $user_email = $this->usersModel->findByEmail($email);

                if (!empty($user_email) && (int) $user_email['id'] !== $user_id) {
                    throw new RuntimeException('Ya existe un usuario registrado con ese email.');
                }
            }

            /*
             * Actualizamos wi_users.
             */
            $this->usersModel->setId($user_id);
            $this->usersModel->setFirstName($name);
            $this->usersModel->setLastName($lastname !== '' ? $lastname : null);
            $this->usersModel->setDisplayName($old_user['display_name']);
            $this->usersModel->setEmail($email);
            $this->usersModel->setPassword($old_user['password']);
            $this->usersModel->setStatus((int) $old_user['status']);
            $this->usersModel->setLastLoginAt($old_user['last_login_at']);
            $this->usersModel->setEmailVerifiedAt($old_user['email_verified_at']);
            $this->usersModel->setAttempts($old_user['attempts'] !== null ? (int) $old_user['attempts'] : null);

            if (!$this->usersModel->update()) {
                throw new RuntimeException('No se han podido actualizar los datos del usuario.');
            }

            /*
             * Actualizamos wi_users_profile.
             */
            $this->usersProfileModel->setUserId($user_id);
            $this->usersProfileModel->setPhone($phone !== '' ? $phone : null);
            $this->usersProfileModel->setPhoto($old_user['photo']);
            $this->usersProfileModel->setCountryId($country_id);
            $this->usersProfileModel->setProvinceId($province_id);
            $this->usersProfileModel->setAddress($address !== '' ? $address : null);
            $this->usersProfileModel->setPostalCode($postal_code !== '' ? $postal_code : null);
            $this->usersProfileModel->setCity($city !== '' ? $city : null);

            
            if ($this->usersProfileModel->existsByUserId($user_id)) {

                if (!$this->usersProfileModel->update()) {
                    throw new RuntimeException('No se han podido actualizar los datos del perfil del usuario.');
                }
            } else {

                if (!$this->usersProfileModel->add()) {
                    throw new RuntimeException('No se ha podido crear el perfil del usuario.');
                }
            }


            /*
             * Auditoría de wi_users.
             */
            $this->audit_service->update(
                    'wi_users',
                    $user_id,
                    $old_user,
                    [
                        'first_name' => $name,
                        'last_name' => $lastname !== '' ? $lastname : null,
                        'email' => $email
                    ],
                    __METHOD__,
                    [],
                    'Actualización de datos del usuario'
            );

            /*
             * Auditoría de wi_users_profile.
             *
             * El target_id sigue siendo el user_id para poder recuperar
             * posteriormente toda la actividad asociada al usuario.
             */
            $this->audit_service->update(
                    'wi_users_profile',
                    $user_id,
                    $old_user,
                    [
                        'phone' => $phone !== '' ? $phone : null,
                        'country_id' => $country_id,
                        'province_id' => $province_id,
                        'address' => $address !== '' ? $address : null,
                        'postal_code' => $postal_code !== '' ? $postal_code : null,
                        'city' => $city !== '' ? $city : null
                    ],
                    __METHOD__,
                    [
                        'profile_id' => $old_user['profile_id']
                    ],
                    'Actualización del perfil del usuario'
            );

            $result['success'] = true;
            $result['message'] = 'Los datos del cliente se han actualizado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function updatePassword(int $user_id, stdClass $params): array {

        try {

            if (empty($user_id)) {
                return serviceResponse::error(
                                'El identificador del usuario no es válido.',
                                'USER_ID_INVALID'
                        );
            }

            $password = (string) ($params->password ?? '');
            $passwordConfirm = (string) ($params->password_confirm ?? '');

            /*
             * Comprobamos que el usuario exista y recuperamos
             * sus datos anteriores para auditoría.
             */
            $old_user = $this->usersModel->findById($user_id, false);

            if (empty($old_user)) {
                return serviceResponse::error(
                                'El usuario no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            /*
             * Validamos contraseña y confirmación utilizando
             * las mismas reglas que en el registro.
             */
            $passwordResponse = $this->passwordService->checkPassword(
                    $password,
                    $passwordConfirm
            );

            if (!$passwordResponse['success']) {
                return $passwordResponse;
            }

            /*
             * Ciframos la nueva contraseña.
             */
            $passwordEncrypted = $this->passwordService->encryptPassword($password);

            /*
             * Actualizamos únicamente la contraseña.
             */
            $this->usersModel->setId($user_id);
            $this->usersModel->setPassword($passwordEncrypted);

            if (!$this->usersModel->updatePassword()) {
                return serviceResponse::error(
                                'No se ha podido actualizar la contraseña.',
                                'PASSWORD_UPDATE_ERROR'
                        );
            }

            /*
             * Auditoría.
             * auditLogsService se encargará de enmascarar
             * el campo password al ser un dato sensible.
             */
            $this->audit_service->update(
                    'wi_users',
                    $user_id,
                    $old_user,
                    [
                        'password' => $passwordEncrypted
                    ],
                    __METHOD__,
                    [],
                    'Cambio de contraseña del usuario'
            );

            return serviceResponse::success(
                            'La contraseña se ha actualizado correctamente.',
                            'PASSWORD_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            'Se ha producido un error al actualizar la contraseña.',
                            'PASSWORD_UPDATE_EXCEPTION'
                    );
        }
    }
    
    public function updatePhoto(int $user_id, stdClass $params, array $files): array {

        try {

            if (empty($user_id)) {
                return serviceResponse::error(
                                'El identificador del usuario no es válido.',
                                'USER_ID_INVALID'
                        );
            }

            /*
             * Recuperamos los datos actuales del usuario y del perfil.
             */
            $user = $this->usersModel->findEditData($user_id);

            if (empty($user)) {
                return serviceResponse::error(
                                'El usuario no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            $old_photo = $user['photo'] ?? null;
            $remove_photo = isset($params->remove_profile_image) && (int) $params->remove_profile_image === 1;

            /*
             * El usuario ha indicado que quiere eliminar la foto actual.
             */
            if ($remove_photo) {

                if (empty($old_photo)) {
                    return serviceResponse::error(
                                    'El usuario no tiene ninguna imagen de perfil.',
                                    'PROFILE_PHOTO_NOT_FOUND'
                            );
                }

                /*
                 * Actualizamos la base de datos eliminando la foto.
                 */
                $this->usersProfileModel->setUserId($user_id);

                if ($this->usersProfileModel->existsByUserId($user_id)) {

                    if (!$this->usersProfileModel->updatePhoto(null)) {
                        throw new RuntimeException('No se ha podido eliminar la imagen de perfil.');
                    }
                }

                /*
                 * Eliminamos el fichero del disco.
                 */
                $file_path = self::PROFILE_IMAGES_PATH . basename($old_photo);

                if (is_file($file_path)) {
                    unlink($file_path);
                }

                /*
                 * Auditoría.
                 */
                $this->audit_service->update(
                        'wi_users_profile',
                        $user_id,
                        $user,
                        [
                            'photo' => null
                        ],
                        __METHOD__,
                        [],
                        'Eliminación de imagen de perfil'
                );

                return serviceResponse::success(
                                'La imagen de perfil se ha eliminado correctamente.',
                                'PROFILE_PHOTO_REMOVED'
                        );
            }


            /*
             * Si no se está eliminando la imagen,
             * debe haberse enviado una nueva.
             */
            if (empty($files['profile_image']) || empty($files['profile_image']['tmp_name'])) {
                return serviceResponse::error(
                                'Debe seleccionar una imagen.',
                                'PROFILE_PHOTO_REQUIRED'
                        );
            }

            $file = $files['profile_image'];

            /*
             * Comprobamos que no haya habido errores durante la subida.
             */
            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                return serviceResponse::error(
                                'Se ha producido un error al subir la imagen.',
                                'PROFILE_PHOTO_UPLOAD_ERROR'
                        );
            }


            /*
             * Validamos el tipo real del fichero.
             */
            $mime = mime_content_type($file['tmp_name']);

            $allowed_types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowed_types[$mime])) {
                return serviceResponse::error(
                                'El formato de imagen no es válido. Solo se permiten JPG, PNG y WEBP.',
                                'PROFILE_PHOTO_INVALID_TYPE'
                        );
            }

            $extension = $allowed_types[$mime];

            /*
             * Generamos el nombre del fichero.
             */
            $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;

            /*
             * Ruta que se guarda en base de datos.
             */
            $relative_path = 'assets/images/usuarios/perfil/' . $filename;

            /*
             * Ruta física donde se guarda el fichero.
             */
            $destination = self::PROFILE_IMAGES_PATH . $filename;

            /*
             * Comprobamos que exista el directorio.
             */
            if (!is_dir(self::PROFILE_IMAGES_PATH)) {
                throw new RuntimeException('No existe el directorio de imágenes de perfil.');
            }


            /*
             * Guardamos la nueva imagen.
             */
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('No se ha podido guardar la imagen de perfil.');
            }


            /*
             * Actualizamos el perfil conservando todos sus demás datos.
             */
            $this->usersProfileModel->setUserId($user_id);

            if ($this->usersProfileModel->existsByUserId($user_id)) {
                if (!$this->usersProfileModel->updatePhoto($relative_path)) {

                    /*
                     * Si falla la actualización de la base de datos,
                     * eliminamos la nueva imagen para no dejarla huérfana.
                     */
                    if (is_file($destination)) {
                        unlink($destination);
                    }

                    throw new RuntimeException('No se ha podido actualizar la imagen de perfil.');
                }
            } else {

                $this->usersProfileModel->setPhoto($relative_path);

                if (!$this->usersProfileModel->add()) {
                    throw new RuntimeException('No se ha podido crear el perfil del usuario.');
                }
            }


            /*
             * Una vez guardada correctamente la nueva imagen,
             * eliminamos la anterior si existía.
             */
            if (!empty($old_photo)) {

                $old_file = self::PROFILE_IMAGES_PATH . basename($old_photo);

                if (is_file($old_file)) {
                    unlink($old_file);
                }
            }


            /*
             * Auditoría.
             */
            $this->audit_service->update(
                    'wi_users_profile',
                    $user_id,
                    $user,
                    [
                        'photo' => $relative_path
                    ],
                    __METHOD__,
                    [],
                    empty($old_photo) ? 'Alta de imagen de perfil' : 'Actualización de imagen de perfil'
            );

            return serviceResponse::success(
                            'La imagen de perfil se ha actualizado correctamente.',
                            'PROFILE_PHOTO_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'PROFILE_PHOTO_UPDATE_ERROR'
                    );
        }
    }
    
    public function updateDisplayName(int $user_id, stdClass $params): array {

        try {

            if (empty($user_id)) {
                return serviceResponse::error(
                                'El identificador del usuario no es válido.',
                                'USER_ID_INVALID'
                        );
            }

            $display_name = trim(strip_tags((string) ($params->display_name ?? '')));
            $display_name = $display_name !== '' ? $display_name : null;

            $user = $this->usersModel->findById($user_id, false);

            if (empty($user)) {
                return serviceResponse::error(
                                'El usuario no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            /*
             * No hacemos nada si el valor no ha cambiado.
             */
            $old_display_name = $user['display_name'] ?? null;

            if ($old_display_name === $display_name) {
                return serviceResponse::success(
                                'No se han realizado cambios.',
                                'DISPLAY_NAME_UNCHANGED'
                        );
            }

            $this->usersModel->setId($user_id);

            if (!$this->usersModel->updateDisplayName($display_name)) {
                return serviceResponse::error(
                                'No se ha podido actualizar el nombre para mostrar.',
                                'DISPLAY_NAME_UPDATE_ERROR'
                        );
            }

            $this->audit_service->update(
                    'wi_users',
                    $user_id,
                    $user,
                    [
                        'display_name' => $display_name
                    ],
                    __METHOD__,
                    [],
                    'Actualización del nombre para mostrar'
            );

            return serviceResponse::success(
                            'El nombre para mostrar se ha actualizado correctamente.',
                            'DISPLAY_NAME_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            'Se ha producido un error al actualizar el nombre para mostrar.',
                            'DISPLAY_NAME_UPDATE_EXCEPTION'
                    );
        }
    }
    
    public function addBonus(int $user_id, stdClass $params): array {

        try {

            if (empty($user_id)) {
                return serviceResponse::error(
                                'El identificador del usuario no es válido.',
                                'USER_ID_INVALID'
                        );
            }

            $bonus_id = isset($params->bonus_id) ? (int) $params->bonus_id : 0;

            if (empty($bonus_id)) {
                return serviceResponse::error(
                                'Debe seleccionar un bono.',
                                'BONUS_REQUIRED'
                        );
            }

            /*
             * Comprobamos que el usuario exista.
             */
            $user = $this->usersModel->findById($user_id, false);

            if (empty($user)) {
                return serviceResponse::error(
                                'El usuario no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            /*
             * Recuperamos el bono.
             */
            $bonus = $this->bonusesModel->findById($bonus_id, false);

            if (empty($bonus)) {
                return serviceResponse::error(
                                'El bono seleccionado no existe.',
                                'BONUS_NOT_FOUND'
                        );
            }

            /*
             * El bono debe estar activo para poder asignarlo.
             */
            if ((int) $bonus['status'] !== bonusesModel::STATUS_ACTIVE) {
                return serviceResponse::error(
                                'El bono seleccionado no está disponible.',
                                'BONUS_NOT_ACTIVE'
                        );
            }

            $valid_from = trim((string) ($params->valid_from ?? ''));

            if ($valid_from === '') {
                return serviceResponse::error(
                                'Debe indicar la fecha de inicio del bono.',
                                'BONUS_VALID_FROM_REQUIRED'
                        );
            }

            try {
                $reference_date = new DateTimeImmutable($valid_from);
            } catch (Throwable $e) {
                return serviceResponse::error(
                                'La fecha de inicio del bono no es válida.',
                                'BONUS_VALID_FROM_INVALID'
                        );
            }

            $validity = $this->calculateBonusValidity($bonus, $reference_date);

            $valid_from = $validity['valid_from'];
            $expires_at = $validity['expires_at'];

            /*
             * Calculamos los usos.
             */
            $initial_uses = 0;
            $remaining_uses = 0;

            if ((string) $bonus['bonus_type'] === bonusesModel::TYPE_USES) {
                $initial_uses = (int) $bonus['total_uses'];
                $remaining_uses = (int) $bonus['total_uses'];
            }

            /*
             * Creamos el bono del usuario copiando las condiciones
             * actuales del bono.
             */
            $this->usersBonusesModel->setUserId($user_id);
            $this->usersBonusesModel->setBonusId($bonus_id);
            $this->usersBonusesModel->setInitialUses($initial_uses);
            $this->usersBonusesModel->setRemainingUses($remaining_uses);
            $this->usersBonusesModel->setPurchasePrice((float) $bonus['price']);
            $this->usersBonusesModel->setValidFrom($valid_from);
            $this->usersBonusesModel->setExpiresAt($expires_at);
            $this->usersBonusesModel->setStatus(usersBonusesModel::STATUS_ACTIVE);

            $user_bonus_id = $this->usersBonusesModel->add();

            if (!$user_bonus_id) {
                return serviceResponse::error(
                                'No se ha podido añadir el bono al usuario.',
                                'USER_BONUS_CREATE_ERROR'
                        );
            }
            
            /*
             * Control de acceso.
             *
             * createBonusPin() ya se encarga de comprobar:
             * - que sea un bono TIME
             * - que esté ACTIVE
             * - que la instalación no requiera reserva
             * - que tenga control de acceso y cerraduras
             */
            $access_warning = '';

            if ((string) $bonus['bonus_type'] === bonusesModel::TYPE_TIME) {

                $access_result = $this->accessPinsService->createBonusPin(
                        (int) $user_bonus_id
                );

                if (!$access_result['success']) {

                    $access_warning = ' El bono se ha activado, pero no se ha podido generar correctamente el acceso: '
                            . $access_result['message'];
                } elseif (!empty($access_result['partial'])) {

                    $access_warning = ' El bono se ha activado, pero el PIN queda pendiente de sincronización en alguna cerradura.';
                }
            }

            $mail_warning = '';

            $mail_service = new mailService();

            $mail_result = $mail_service->sendBonusConfirmed(
                    [
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name']
                    ],
                    [
                        'name' => $bonus['name'],
                        'bonus_type' => $bonus['bonus_type'],
                        'total_uses' => $bonus['total_uses'],
                        'purchase_price' => (float) $bonus['price'],
                        'valid_from' => $valid_from,
                        'expires_at' => $expires_at
                    ],
                    $access_result
            );

            if (empty($mail_result['success'])) {
                $mail_warning = ' ' . ($mail_result['message'] ?? 'No se ha podido enviar el correo informativo.');
            }

            $this->audit_service->insert(
                    'wi_users_bonuses',
                    $user_id,
                    [
                        'id' => $user_bonus_id,
                        'user_id' => $user_id,
                        'bonus_id' => $bonus_id,
                        'initial_uses' => $initial_uses,
                        'remaining_uses' => $remaining_uses,
                        'purchase_price' => (float) $bonus['price'],
                        'valid_from' => $valid_from,
                        'expires_at' => $expires_at,
                        'status' => usersBonusesModel::STATUS_ACTIVE
                    ],
                    __METHOD__,
                    'Asignación de bono al usuario'
            );

            return serviceResponse::success(
                            'El bono se ha añadido correctamente.' . $access_warning . $mail_warning,
                            'USER_BONUS_CREATED',
                            [
                                'user_bonus_id' => $user_bonus_id
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'USER_BONUS_CREATE_EXCEPTION'
                    );
        }
    }

    /**
     * Elimina lógicamente un bono asignado a un usuario.
     *
     * @param int $bonus_id Id de wi_users_bonuses.
     *
     * @return array
     */
    public function deleteBonus(int $bonus_id): array {

        try {

            if (empty($bonus_id)) {
                return serviceResponse::error(
                                'El identificador del bono no es válido.',
                                'BONUS_ID_INVALID'
                        );
            }

            /*
             * Obtenemos los datos actuales del bono para comprobar
             * que existe y registrar posteriormente la auditoría.
             */
            $bonus = $this->usersBonusesModel->findById($bonus_id, false);
            $bonus_detail = $this->usersBonusesModel->findDetailById($bonus_id, false);

            if (empty($bonus)) {
                return serviceResponse::error(
                                'El bono no existe.',
                                'BONUS_NOT_FOUND'
                        );
            }

            /*
             * Si ya está eliminado no hacemos nada.
             */
            if ((int) $bonus['status'] === usersBonusesModel::STATUS_DELETED) {
                return serviceResponse::success(
                                'El bono ya está eliminado.',
                                'BONUS_ALREADY_DELETED'
                        );
            }

            /*
             * Si es un bono TIME, antes de eliminarlo retiramos
             * todos los PIN de acceso asociados.
             */
            if (!empty($bonus_detail) && (string) $bonus_detail['bonus_type'] === bonusesModel::TYPE_TIME) {

                $revoke_result = $this->accessPinsService->revokeBonusPin($bonus_id);

                if (!$revoke_result['success']) {
                    return serviceResponse::error(
                                    'No se ha podido eliminar el bono porque no se ha podido revocar completamente su acceso: '
                                    . $revoke_result['message'],
                                    'BONUS_ACCESS_REVOKE_ERROR'
                            );
                }
            }

            /*
             * Realizamos el borrado lógico.
             */
            $this->usersBonusesModel->setId($bonus_id);

            if (!$this->usersBonusesModel->softDelete()) {
                return serviceResponse::error(
                                'No se ha podido eliminar el bono.',
                                'BONUS_DELETE_ERROR'
                        );
            }

            /*
             * Auditoría.
             *
             * El target_id es el user_id para que el evento aparezca
             * dentro de la auditoría del usuario.
             */
            $this->audit_service->update(
                    'wi_users_bonuses',
                    (int) $bonus['user_id'],
                    $bonus,
                    [
                        'status' => usersBonusesModel::STATUS_DELETED,
                        'deleted_at' => date('Y-m-d H:i:s')
                    ],
                    __METHOD__,
                    [
                        'user_bonus_id' => $bonus_id
                    ],
                    'Eliminación de bono del usuario'
            );

            $mail_warning = null;

            if (!empty($bonus_detail)) {

                $user_model = new usersModel();
                $user = $user_model->findById((int) $bonus['user_id'], false);

                if (!empty($user)) {

                    $mail_service = new mailService();

                    $mail_result = $mail_service->sendBonusCancelled(
                            $user,
                            $bonus_detail
                    );

                    if (!$mail_result['success']) {
                        $mail_warning = ' ' . $mail_result['message'];
                    }
                }
            }

            $message = 'El bono se ha eliminado correctamente.';

            if ($mail_warning !== null) {
                $message .= $mail_warning;
            }

            return serviceResponse::success(
                            $message,
                            'BONUS_DELETED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'BONUS_DELETE_EXCEPTION'
                    );
        }
    }

    public function buyBonus(int $user_id, stdClass $params): array {

        try {

            $bonus_id = isset($params->bonus_id) ? (int) $params->bonus_id : 0;
            $payment_method_id = isset($params->payment_method_id) ? (int) $params->payment_method_id : 0;

            if ($user_id <= 0) {
                return serviceResponse::error(
                                'El usuario no es válido.',
                                'USER_INVALID'
                        );
            }

            if ($bonus_id <= 0) {
                return serviceResponse::error(
                                'El bono seleccionado no es válido.',
                                'BONUS_INVALID'
                        );
            }

            if ($payment_method_id <= 0) {
                return serviceResponse::error(
                                'Debe seleccionar un método de pago.',
                                'PAYMENT_METHOD_REQUIRED'
                        );
            }

            /*
             * Recuperamos el bono.
             */
            $facility_bonus_model = new bonusesModel();

            $bonus = $facility_bonus_model->findById(
                    $bonus_id
            );

            if (empty($bonus)) {
                return serviceResponse::error(
                                'El bono seleccionado no existe.',
                                'BONUS_NOT_FOUND'
                        );
            }

            if ((int) $bonus['status'] !== bonusesModel::STATUS_ACTIVE) {
                return serviceResponse::error(
                                'El bono seleccionado no está disponible.',
                                'BONUS_NOT_AVAILABLE'
                        );
            }

            $bonuses_facilities_model = new bonusesFacilitiesModel();

            $facilities = $bonuses_facilities_model->findFacilitiesByBonusId(
                    $bonus_id,
                    false
            );

            $facility_names = [];

            foreach ($facilities as $facility) {
                if (!empty($facility['name'])) {
                    $facility_names[] = $facility['name'];
                }
            }

            $facilities_text = implode(', ', $facility_names);

            if (empty($facility_names)) {
                return serviceResponse::error(
                                'El bono no está asociado a ninguna instalación.',
                                'BONUS_WITHOUT_FACILITIES'
                        );
            }

            /*
             * Comprobamos que el método de pago seleccionado
             * pertenece al bono.
             */
            $bonus_payment_methods_model = new bonusesPaymentMethodsModel();

            $bonus_payment_method = $bonus_payment_methods_model->findByBonusAndPaymentMethod(
                    $bonus_id,
                    $payment_method_id
            );

            if (empty($bonus_payment_method)) {
                return serviceResponse::error(
                                'El método de pago seleccionado no está disponible para este bono.',
                                'PAYMENT_METHOD_NOT_AVAILABLE'
                        );
            }

            /*
             * Comprobamos también que el método de pago
             * sigue estando activo.
             */
            $payment_methods_model = new paymentMethodsModel();

            $payment_method = $payment_methods_model->findById(
                    $payment_method_id
            );

            if (empty($payment_method) || (int) $payment_method['status'] !== paymentMethodsModel::STATUS_ACTIVE) {
                return serviceResponse::error(
                                'El método de pago seleccionado no está disponible.',
                                'PAYMENT_METHOD_NOT_AVAILABLE'
                        );
            }

            /*
             * De momento no existe pago online.
             *
             * Todos los bonos comprados por un cliente
             * quedan pendientes de confirmación.
             */
            $status = usersBonusesModel::STATUS_INACTIVE;
            $valid_from = null;
            $expires_at = null;
            
            $initial_uses = 0;
            $remaining_uses = 0;

            if ((string) $bonus['bonus_type'] === bonusesModel::TYPE_USES) {
                $initial_uses = (int) $bonus['total_uses'];
                $remaining_uses = (int) $bonus['total_uses'];
            }

            /*
             * Creamos el bono del usuario.
             */
            $this->usersBonusesModel->setUserId($user_id);
            $this->usersBonusesModel->setBonusId($bonus_id);
            $this->usersBonusesModel->setInitialUses($initial_uses);
            $this->usersBonusesModel->setRemainingUses($remaining_uses);
            $this->usersBonusesModel->setPurchasePrice((float) $bonus['price']);
            $this->usersBonusesModel->setPaymentMethodId($payment_method_id);
            $this->usersBonusesModel->setValidFrom($valid_from);
            $this->usersBonusesModel->setExpiresAt($expires_at);
            $this->usersBonusesModel->setStatus($status);

            $user_bonus_id = $this->usersBonusesModel->add();

            if ($user_bonus_id <= 0) {
                throw new RuntimeException(
                                'No se ha podido registrar la compra del bono.'
                        );
            }
            
            $this->audit_service->insert(
                    'wi_users_bonuses',
                    $user_id,
                    [
                        'user_bonus_id' => $user_bonus_id,
                        'user_id' => $user_id,
                        'bonus_id' => $bonus_id,
                        'initial_uses' => $initial_uses,
                        'remaining_uses' => $remaining_uses,
                        'purchase_price' => (float) $bonus['price'],
                        'payment_method_id' => $payment_method_id,
                        'valid_from' => null,
                        'expires_at' => null,
                        'status' => usersBonusesModel::STATUS_INACTIVE
                    ],
                    __METHOD__,
                    'Solicitud de compra de bono'
            );
            
            $mail_warning = '';

            $mail_service = new mailService();

            $user_model = new usersModel();
            $user = $user_model->findById($user_id);

            if (!empty($user)) {

                $mail_result = $mail_service->sendPendingBonus(
                        $user,
                        $facilities_text,
                        $bonus
                );

                if (empty($mail_result['success'])) {
                    $mail_warning = ' ' . ($mail_result['message'] ?? 'No se ha podido enviar el correo informativo.');
                }
            }

            return serviceResponse::success(
                            'El bono se ha solicitado correctamente y queda pendiente de confirmación.' . $mail_warning,
                            'BONUS_PURCHASE_PENDING',
                            [
                                'user_bonus_id' => $user_bonus_id
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'BONUS_PURCHASE_EXCEPTION'
                    );
        }
    }
    
    public function confirmBonus(int $bonus_id): array {

        try {

            if ($bonus_id <= 0) {
                return serviceResponse::error(
                                'El identificador del bono no es válido.',
                                'BONUS_ID_INVALID'
                        );
            }

            $bonus = $this->usersBonusesModel->findDetailById($bonus_id, false);

            if (empty($bonus)) {
                return serviceResponse::error(
                                'El bono no existe.',
                                'BONUS_NOT_FOUND'
                        );
            }

            if ((int) $bonus['status'] === usersBonusesModel::STATUS_ACTIVE) {
                return serviceResponse::success(
                                'El bono ya está activo.',
                                'BONUS_ALREADY_ACTIVE'
                        );
            }

            if ((int) $bonus['status'] !== usersBonusesModel::STATUS_INACTIVE) {
                return serviceResponse::error(
                                'El bono no se encuentra pendiente de confirmación.',
                                'BONUS_STATUS_INVALID'
                        );
            }

            /*
             * La validez comienza en el momento
             * en que el gestor confirma el bono.
             */
            $reference_date = new DateTimeImmutable();

            $validity = $this->calculateBonusValidity($bonus, $reference_date);

            $valid_from = $validity['valid_from'];
            $expires_at = $validity['expires_at'];

            if (!$this->usersBonusesModel->activate($bonus_id, $valid_from, $expires_at)) {
                return serviceResponse::error(
                                'No se ha podido activar el bono.',
                                'BONUS_ACTIVATE_ERROR'
                        );
            }
            
            /*
             * Control de acceso.
             *
             * En este punto el bono ya está ACTIVE y ya tiene
             * sus fechas definitivas de validez.
             */
            $access_warning = '';

            if ((string) $bonus['bonus_type'] === bonusesModel::TYPE_TIME) {

                $access_result = $this->accessPinsService->createBonusPin(
                        $bonus_id
                );

                if (!$access_result['success']) {

                    $access_warning = ' El bono se ha activado, pero no se ha podido generar correctamente el acceso: '
                            . $access_result['message'];
                } elseif (!empty($access_result['partial'])) {

                    $access_warning = ' El bono se ha activado, pero el PIN queda pendiente de sincronización en alguna cerradura.';
                }
            }

            /*
             * Auditoría.
             *
             * target_id = usuario propietario del bono.
             */
            $this->audit_service->update(
                    'wi_users_bonuses',
                    (int) $bonus['user_id'],
                    $bonus,
                    [
                        'status' => usersBonusesModel::STATUS_ACTIVE,
                        'bonus_type' => $bonus['bonus_type'],
                        'valid_from' => $valid_from,
                        'expires_at' => $expires_at
                    ],
                    __METHOD__,
                    [
                        'user_bonus_id' => $bonus_id
                    ],
                    'Confirmación y activación de bono'
            );

            /*
             * Envío de correo.
             *
             * Si falla, el bono permanece activado.
             */
            $mail_warning = '';

            $mail_service = new mailService();

            $mail_result = $mail_service->sendBonusConfirmed(
                    [
                        'email' => $bonus['email'],
                        'first_name' => $bonus['first_name'],
                        'last_name' => $bonus['last_name']
                    ],
                    [
                        'name' => $bonus['bonus_name'],
                        'bonus_type' => $bonus['bonus_type'],
                        'total_uses' => $bonus['total_uses'],
                        'purchase_price' => $bonus['purchase_price'],
                        'valid_from' => $valid_from,
                        'expires_at' => $expires_at
                    ],
                    $access_result
            );

            if (empty($mail_result['success'])) {
                $mail_warning = ' ' . ($mail_result['message'] ?? 'No se ha podido enviar el correo informativo.');
            }

            return serviceResponse::success(
                            'El bono se ha activado correctamente.' . $access_warning . $mail_warning,
                            'BONUS_ACTIVATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'BONUS_ACTIVATE_EXCEPTION'
                    );
        }
    }

    public function createUserFromAdmin(int $role, stdClass $params): array {

        try {

            $first_name = trim(strip_tags((string) ($params->first_name ?? '')));
            $last_name = trim(strip_tags((string) ($params->last_name ?? '')));
            $email = strtolower(trim((string) ($params->email ?? '')));

            $phone = trim(strip_tags((string) ($params->phone ?? '')));
            $address = trim(strip_tags((string) ($params->address ?? '')));
            $city = trim(strip_tags((string) ($params->city ?? '')));
            $postal_code = trim(strip_tags((string) ($params->postal_code ?? '')));

            $country_id = !empty($params->country_id) ? (int) $params->country_id : null;
            $province_id = !empty($params->province_id) ? (int) $params->province_id : null;

            if ($first_name === '') {
                return serviceResponse::error(
                                'El nombre es obligatorio.',
                                'NAME_REQUIRED'
                        );
            }

            if ($last_name === '') {
                return serviceResponse::error(
                                'Los apellidos son obligatorios.',
                                'SURNAME_REQUIRED'
                        );
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return serviceResponse::error(
                                'El correo electrónico no es válido.',
                                'INVALID_EMAIL'
                        );
            }

            if (empty($role)) {
                return serviceResponse::error(
                                'El rol del usuario no es válido.',
                                'ROLE_INVALID'
                        );
            }

            if ($this->usersModel->findByEmail($email) !== null) {
                return serviceResponse::error(
                                'Ya existe un usuario registrado con ese correo electrónico.',
                                'EMAIL_ALREADY_EXISTS'
                        );
            }

            /*
             * Generamos una contraseña interna aleatoria.
             *
             * No se comunica al usuario. Será sustituida cuando
             * establezca su contraseña desde el enlace de activación.
             */
            $temporary_password = bin2hex(random_bytes(32));
            $passwordEncrypted = $this->passwordService->encryptPassword($temporary_password);

            /*
             * Creamos el usuario inicialmente inactivo.
             */
            $this->usersModel->setFirstName($first_name);
            $this->usersModel->setLastName($last_name);
            $this->usersModel->setDisplayName(trim($first_name . ' ' . $last_name));
            $this->usersModel->setEmail($email);
            $this->usersModel->setPassword($passwordEncrypted);
            $this->usersModel->setStatus(usersModel::STATUS_INACTIVE);

            $user_id = $this->usersModel->add();

            if (!$user_id) {
                return serviceResponse::error(
                                'No se ha podido crear el usuario.',
                                'USER_CREATE_ERROR'
                        );
            }

            /*
             * Añadimos el rol.
             */
            $model_role = new usersRolesModel();
            $model_role->setRoleId($role);
            $model_role->setUserId($user_id);

            if (!$model_role->add()) {
                return serviceResponse::error(
                                'No se ha podido asignar el rol al usuario.',
                                'USER_ROLE_CREATE_ERROR'
                        );
            }

            /*
             * Creamos el perfil únicamente si se ha recibido algún dato.
             */
            $has_profile_data = $phone !== '' || $address !== '' || $city !== '' || $postal_code !== '' || $country_id !== null || $province_id !== null;

            if ($has_profile_data) {

                $this->usersProfileModel->setUserId($user_id);
                $this->usersProfileModel->setPhone($phone !== '' ? $phone : null);
                $this->usersProfileModel->setCountryId($country_id);
                $this->usersProfileModel->setProvinceId($province_id);
                $this->usersProfileModel->setAddress($address !== '' ? $address : null);
                $this->usersProfileModel->setPostalCode($postal_code !== '' ? $postal_code : null);
                $this->usersProfileModel->setCity($city !== '' ? $city : null);

                if (!$this->usersProfileModel->add()) {
                    return serviceResponse::error(
                                    'El usuario se ha creado, pero no se ha podido guardar su perfil.',
                                    'USER_PROFILE_CREATE_ERROR'
                            );
                }
            }

            /*
             * Auditoría del usuario.
             */
            $this->audit_service->insert(
                    'wi_users',
                    $user_id,
                    [
                        'id' => $user_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'display_name' => trim($first_name . ' ' . $last_name),
                        'email' => $email,
                        'status' => usersModel::STATUS_INACTIVE
                    ],
                    __METHOD__,
                    'Creación de usuario'
            );

            /*
             * Auditoría del perfil, si se ha creado.
             */
            if ($has_profile_data) {

                $this->audit_service->insert(
                        'wi_users_profile',
                        $user_id,
                        [
                            'user_id' => $user_id,
                            'phone' => $phone !== '' ? $phone : null,
                            'country_id' => $country_id,
                            'province_id' => $province_id,
                            'address' => $address !== '' ? $address : null,
                            'postal_code' => $postal_code !== '' ? $postal_code : null,
                            'city' => $city !== '' ? $city : null
                        ],
                        __METHOD__,
                        'Creación del perfil del usuario'
                );
            }

            return serviceResponse::success(
                            'Usuario creado correctamente.',
                            'USER_CREATED',
                            [
                                'user_id' => $user_id,
                                'email' => $email,
                                'name' => $first_name
                            ]
                    );
        } catch (Throwable $e) {

            $log = new logsModel(
                    'usuarios',
                    'users_service.log'
            );

            $log->info($e->getMessage());

            return serviceResponse::error(
                            'No se ha podido crear el usuario',
                            'USER_CREATE_EXCEPTION'
                    );
        }
    }
    
    public function validateSetPasswordToken(string $token): array {

        $token = trim($token);

        if ($token === '') {
            return serviceResponse::error(
                            'El enlace no es válido.',
                            'SET_PASSWORD_TOKEN_EMPTY'
                    );
        }

        try {

            $tokenHash = hash('sha256', $token);

            $tokenData = $this->userTokensModel->findValidToken(
                    $tokenHash,
                    'SET_PASSWORD'
            );

            if ($tokenData === null) {
                return serviceResponse::error(
                                'El enlace no es válido, ha caducado o ya ha sido utilizado.',
                                'SET_PASSWORD_TOKEN_INVALID'
                        );
            }

            return serviceResponse::success(
                            'El enlace es válido.',
                            'SET_PASSWORD_TOKEN_VALID',
                            [
                                'user_id' => (int) $tokenData['user_id']
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            'Se ha producido un error al validar el enlace.',
                            'SET_PASSWORD_TOKEN_EXCEPTION'
                    );
        }
    }
    
    public function setPassword(
            string $token,
            string $password,
            string $passwordConfirm
    ): array {

        $token = trim($token);

        if ($token === '') {
            return serviceResponse::error(
                            'El enlace no es válido.',
                            'SET_PASSWORD_TOKEN_EMPTY'
                    );
        }

        try {

            /*
             * Validamos primero la contraseña con las mismas reglas
             * utilizadas durante el registro.
             */
            $passwordResponse = $this->passwordService->checkPassword(
                    $password,
                    $passwordConfirm
            );

            if (!$passwordResponse['success']) {
                return $passwordResponse;
            }


            /*
             * Buscamos el token válido.
             */
            $tokenHash = hash('sha256', $token);

            $tokenData = $this->userTokensModel->findValidToken(
                    $tokenHash,
                    'SET_PASSWORD'
            );

            if ($tokenData === null) {
                return serviceResponse::error(
                                'El enlace no es válido, ha caducado o ya ha sido utilizado.',
                                'SET_PASSWORD_TOKEN_INVALID'
                        );
            }

            $userId = (int) $tokenData['user_id'];
            $tokenId = (int) $tokenData['id'];

            /*
             * Recuperamos el usuario antes de modificarlo
             * para la auditoría.
             */
            $oldUser = $this->usersModel->findById(
                    $userId,
                    false
            );

            if (empty($oldUser)) {
                return serviceResponse::error(
                                'El usuario no existe.',
                                'USER_NOT_FOUND'
                        );
            }


            /*
             * Ciframos y guardamos la contraseña definitiva.
             */
            $passwordEncrypted = $this->passwordService->encryptPassword(
                    $password
            );

            $this->usersModel->setId($userId);
            $this->usersModel->setPassword($passwordEncrypted);

            if (!$this->usersModel->updatePassword()) {
                return serviceResponse::error(
                                'No se ha podido guardar la contraseña.',
                                'SET_PASSWORD_UPDATE_ERROR'
                        );
            }


            /*
             * Activamos la cuenta.
             */
            if (!$this->usersModel->updateStatus(
                            usersModel::STATUS_ACTIVE
                    )) {
                return serviceResponse::error(
                                'No se ha podido activar la cuenta.',
                                'ACCOUNT_ACTIVATION_ERROR'
                        );
            }


            /*
             * Marcamos el email como verificado.
             */
            if (!$this->usersModel->markEmailVerified()) {
                return serviceResponse::error(
                                'No se ha podido verificar el correo electrónico.',
                                'EMAIL_VERIFICATION_ERROR'
                        );
            }


            /*
             * Marcamos el token como utilizado.
             */
            if (!$this->userTokensModel->markAsUsed($tokenId)) {
                return serviceResponse::error(
                                'No se ha podido completar el proceso.',
                                'SET_PASSWORD_TOKEN_UPDATE_ERROR'
                        );
            }


            /*
             * Recuperamos el usuario ya actualizado para obtener
             * los valores reales guardados en base de datos.
             */
            $newUser = $this->usersModel->findById(
                    $userId,
                    false
            );

            /*
             * Auditoría.
             */
            $this->audit_service->update(
                    'wi_users',
                    $userId,
                    $oldUser,
                    [
                        'password' => $passwordEncrypted,
                        'status' => $newUser['status'],
                        'email_verified_at' => $newUser['email_verified_at']
                    ],
                    __METHOD__,
                    [],
                    'Establecimiento de contraseña y activación de cuenta'
            );

            return serviceResponse::success(
                            'La contraseña se ha establecido correctamente. Ya puede iniciar sesión.',
                            'SET_PASSWORD_SUCCESS'
                    );
        } catch (Throwable $e) {

            $log = new logsModel(
                    'web',
                    'setPassword.log'
            );

            $log->error(
                    'Error al establecer la contraseña del usuario',
                    [
                        'exception' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
            );

            return serviceResponse::error(
                            'Se ha producido un error al establecer la contraseña.',
                            'SET_PASSWORD_EXCEPTION'
                    );
        }
    }
    
    public function deleteUser(int $user_id): array {

        try {

            if (empty($user_id)) {
                return serviceResponse::error(
                                'El identificador del usuario no es válido.',
                                'USER_ID_INVALID'
                        );
            }

            /*
             * Recuperamos los datos actuales del usuario
             * para comprobar que existe y para auditoría.
             */
            $user = $this->usersModel->findById($user_id);

            if (empty($user)) {
                return serviceResponse::error(
                                'El usuario no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            /*
             * Si ya está eliminado, no hacemos nada.
             */
            if ((int) $user['status'] === usersModel::STATUS_DELETED) {
                return serviceResponse::success(
                                'El usuario ya está eliminado.',
                                'USER_ALREADY_DELETED'
                        );
            }

            /*
             * Borrado lógico.
             */
            $this->usersModel->setId($user_id);

            if (!$this->usersModel->softDelete()) {
                return serviceResponse::error(
                                'No se ha podido eliminar el usuario.',
                                'USER_DELETE_ERROR'
                        );
            }

            /*
             * Auditoría.
             */
            $this->audit_service->update(
                    'wi_users',
                    $user_id,
                    $user,
                    [
                        'status' => usersModel::STATUS_DELETED,
                        'deleted_at' => date('Y-m-d H:i:s')
                    ],
                    __METHOD__,
                    [],
                    'Eliminación de usuario'
            );

            return serviceResponse::success(
                            'El usuario se ha eliminado correctamente.',
                            'USER_DELETED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'USER_DELETE_EXCEPTION'
                    );
        }
    }

    public function unlockBlockedUsers(): array {

        $result = [
            'success' => true,
            'message' => '',
            'total' => 0,
            'unlocked' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {

            $users = $this->usersModel->findBlockedByAttempts(false);

            if (empty($users)) {

                $result['message'] = 'No hay usuarios bloqueados por intentos fallidos.';

                return $result;
            }

            $result['total'] = count($users);

            foreach ($users as $user) {

                $user_id = (int) $user['id'];

                if (!$this->usersModel->unlockByAttempts($user_id)) {

                    $result['success'] = false;
                    $result['errors']++;

                    $result['error_details'][] = [
                        'user_id' => $user_id,
                        'email' => $user['email'] ?? '',
                        'message' => 'No se ha podido desbloquear el usuario.'
                    ];

                    continue;
                }

                $result['unlocked']++;
            }

            if ($result['errors'] > 0) {

                $result['message'] = 'Proceso finalizado con errores. '
                        . $result['unlocked'] . ' usuarios desbloqueados y '
                        . $result['errors'] . ' con errores.';
            } else {

                $result['message'] = 'Se han desbloqueado correctamente '
                        . $result['unlocked'] . ' usuarios.';
            }
        } catch (Throwable $e) {

            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function calculateBonusValidity(array $bonus, DateTimeImmutable $reference_date): array {

        $validity_mode = (string) $bonus['validity_mode'];
        $bonus_type = (string) $bonus['bonus_type'];

        if ($validity_mode === bonusesModel::VALIDITY_DAYS) {

            $validity_days = $bonus['validity_days'] !== null ? (int) $bonus['validity_days'] : null;

            if ($validity_days === null) {

                if ($bonus_type === bonusesModel::TYPE_TIME) {
                    throw new RuntimeException('La validez del bono no es correcta.');
                }

                $valid_from = $reference_date->format('Y-m-d 00:00:00');
                $expires_at = null;
            } else {

                if ($validity_days <= 0) {
                    throw new RuntimeException('La validez del bono no es correcta.');
                }

                $valid_from = $reference_date->format('Y-m-d 00:00:00');
                $expires_at = $reference_date->modify('+' . $validity_days . ' days')->format('Y-m-d 00:00:00');
            }
        } elseif ($validity_mode === bonusesModel::VALIDITY_CALENDAR_WEEK) {

            $valid_from = $reference_date->modify('monday this week')->format('Y-m-d 00:00:00');
            $expires_at = $reference_date->modify('sunday this week')->format('Y-m-d 23:59:59');
        } elseif ($validity_mode === bonusesModel::VALIDITY_CALENDAR_MONTH) {

            $valid_from = $reference_date->modify('first day of this month')->format('Y-m-d 00:00:00');
            $expires_at = $reference_date->modify('last day of this month')->format('Y-m-d 23:59:59');
        } elseif ($validity_mode === bonusesModel::VALIDITY_CALENDAR_YEAR) {

            $valid_from = $reference_date->format('Y') . '-01-01 00:00:00';
            $expires_at = $reference_date->format('Y') . '-12-31 23:59:59';
        } else {

            throw new RuntimeException('El tipo de validez del bono no es válido.');
        }

        return [
            'valid_from' => $valid_from,
            'expires_at' => $expires_at
        ];
    }
}
