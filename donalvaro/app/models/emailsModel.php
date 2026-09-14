<?php

declare(strict_types=1);

class emailsModel extends baseModel
{
    protected const TABLE = 'wi_emails';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private ?int $user_id = null;
    private string $email = '';
    private string $subject = '';
    private string $body = '';
    private ?string $attach = null;
    private int $type_email_id = 1;
    private int $show_in_log = 0;
    private int $deleted = 0;
    private string $created_at = '';
    private ?string $deleted_at = null;


    public function findByEmail(string $email): ?array
    {
        return $this->db()->selectOne(
            'SELECT *
               FROM ' . self::TABLE . '
              WHERE email = ?
              LIMIT 1',
            [strtolower(trim($email))]
        );
    }

    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                user_id,
                email,
                subject,
                body,
                attach,
                type_email_id,
                show_in_log,
                deleted
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?
            )',
            [
                $this->user_id,
                $this->email,
                $this->subject,
                $this->body,
                $this->attach,
                $this->type_email_id,
                $this->show_in_log,
                $this->deleted
            ]
        );
    }

    public function update(): bool
    {
        if (empty($this->id)) {
            throw new RuntimeException('No se puede actualizar sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    user_id = ?,
                    email = ?,
                    subject = ?,
                    body = ?,
                    attach = ?,
                    type_email_id = ?,
                    show_in_log = ?,
                    deleted = ?
              WHERE id = ?',
            [
                $this->user_id,
                $this->email,
                $this->subject,
                $this->body,
                $this->attach,
                $this->type_email_id,
                $this->show_in_log,
                $this->deleted,
                $this->id
            ]
        );
    }

    public function softDelete(): bool
    {
        if ($this->id === null) {
            throw new RuntimeException('No se puede eliminar sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    deleted_at = NOW()
              WHERE id = ?',
            [$this->id]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getAttach(): ?string
    {
        return $this->attach;
    }

    public function getTypeEmailId(): int
    {
        return $this->type_email_id;
    }

    public function getShowInLog(): int
    {
        return $this->show_in_log;
    }

    public function getDeleted(): int
    {
        return $this->deleted;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getDeletedAt(): ?string
    {
        return $this->deleted_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUserId(?int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function setAttach(?string $attach): void
    {
        $this->attach = $attach;
    }

    public function setTypeEmailId(int $type_email_id): void
    {
        $this->type_email_id = $type_email_id;
    }

    public function setShowInLog(int $show_in_log): void
    {
        $this->show_in_log = $show_in_log;
    }

    public function setDeleted(int $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function setDeletedAt(?string $deleted_at): void
    {
        $this->deleted_at = $deleted_at;
    }

}
