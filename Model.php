<?php

/**
 * Class Model
 * Class works with database `testtask`
 */
class Model
{
    /**
     * Instance of mysqli object which is used to set connection to database `testtask`.
     * @var
     */
    public $db;

    /**
     * Title of table `comments` in database.
     */
    const COMMENTS = 'comments';

    const FILE_UPLOAD_DIR = 'uploads/';

    const ADMIN_LOGIN = 'admin';
    const ADMIN_PASSWORD_HASH = '$2y$10$rjlwRSdRUeZpJzCdtWCRweJLU1ySmghM3CQqpJP4fHmoSCn8kdE12';

    /**
     * Constructor used to set connection to the database `testtask`.
     * Executes (connection is set) when new instance of Model is created.
     */
    function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME;
        try {
            $this->db = new PDO($dsn, DB_USER, DB_PASSWORD);
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    /**
     * Inserts comment into database `testtask` into table `comments`
     */
    public function saveNewComment()
    {
        $uploadfile = null;
        if (!empty($_FILES['logo']['name'])) {
            //Check mime type of the uploaded file
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if (false === $ext = array_search(
                    $finfo->file($_FILES['logo']['tmp_name']),
                    array(
                        'jpg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                    ),
                    true
                )
            ) {
                session_start();
                $_SESSION['fileTypeError'] = 'Wrong file type was uploaded! Allowed types: JPG, GIF, PNG.';
                header("Location: /");
                die;
            }

            $info = pathinfo($_FILES['logo']['name']);
            $newName = md5($_POST['name'] . $_FILES['userFile']['tmp_name']) . time() . "." . $info['extension'];
            $uploadfile = self::FILE_UPLOAD_DIR . $newName;

            move_uploaded_file($_FILES['logo']['tmp_name'], $uploadfile);
        }

        $name = $this->purifyVal($_POST['name']);
        $email = $this->purifyVal($_POST['email']);
        $message = $this->purifyVal($_POST['message']);

        if (empty($name) || empty($email) || empty($message)) {
            session_start();
            $_SESSION['error'] = 'Some fields missing. Check all fields.';
            header("Location: /");
            die;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session_start();
            $_SESSION['error'] = 'Wrong email.';
            header("Location: /");
            die;
        }

        $preparedQuery = "INSERT INTO `" . DB_NAME . "`.`" . self::COMMENTS
            . "` (`name` , `email` , `message` , `created_at` , `logo`)
            VALUES (:thename , :email, :message, :created_at, :logo)";
        $query = $this->db->prepare($preparedQuery);
        $query->bindParam(':thename', $name);
        $query->bindParam(':email', $email);
        $query->bindParam(':message', $message);
        $query->bindParam(':created_at', time());
        $query->bindParam(':logo', $uploadfile);
        $query->execute();
    }

    /**
     * Inserts comment into database `testtask` into table `comments`
     * @param $id
     */
    public function updateComment($id)
    {
        $name = $this->purifyVal($_POST['name']);
        $email = $this->purifyVal($_POST['email']);
        $message = $this->purifyVal($_POST['message']);

        if (empty($name) || empty($email) || empty($message)) {
            session_start();
            $_SESSION['error'] = 'Some fields missing. Check all fields.';
            header("Location: /?r=edit&id=" . $id);
            die;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session_start();
            $_SESSION['error'] = 'Wrong email.';
            header("Location: /");
            die;
        }

        $preparedQuery = "UPDATE `" . DB_NAME . "`.`" . self::COMMENTS .
            "` SET name=:thename,
                    email=:email,
                    message=:message,
                    changed_by_admin=:changed_by_admin
                    WHERE id=:id";
        $changedByAdmin = 1;
        $query = $this->db->prepare($preparedQuery);
        $query->bindParam(':thename', $name);
        $query->bindParam(':email', $email);
        $query->bindParam(':message', $message);
        $query->bindParam(':changed_by_admin', $changedByAdmin);
        $query->bindParam(':id', $id);
        $query->execute();
    }

    public function getComments($sortValue, $sortOrder)
    {
        $preparedQuery = "SELECT  `name`, `email`, `message`, `is_approved`, `created_at`, `logo`, `changed_by_admin`
         FROM `" . DB_NAME . "`.`" . self::COMMENTS .
            "` WHERE is_approved=:is_approved ORDER BY `" . $sortValue . "` " . strtoupper($sortOrder);
        $query = $this->db->prepare($preparedQuery);
        $query->execute([':is_approved' => 1]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdminComments($sortValue, $sortOrder)
    {
        $preparedQuery = "SELECT  `id`, `name`, `email`, `message`, `is_approved`, `created_at`, `logo`, `changed_by_admin`
         FROM `" . DB_NAME . "`.`" . self::COMMENTS .
            "` WHERE is_approved IN (:is_approved, 0) ORDER BY `" . $sortValue . "` " . strtoupper($sortOrder);
        $query = $this->db->prepare($preparedQuery);
        $query->execute([':is_approved' => 1]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getComment($id)
    {
        $preparedQuery = "SELECT  `id`, `name`, `email`, `message`, `is_approved`, `created_at`, `logo`
         FROM `" . DB_NAME . "`.`" . self::COMMENTS .
            "` WHERE id=:id";
        $query = $this->db->prepare($preparedQuery);
        $query->execute([':id' => $id]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approveComment($id, $isApproved)
    {
        $preparedQuery = "UPDATE `" . DB_NAME . "`.`" . self::COMMENTS .
            "` SET is_approved=:is_approved WHERE id=:id";
        $query = $this->db->prepare($preparedQuery);
        $query->bindParam(':is_approved', $isApproved);
        $query->bindParam(':id', $id);
        $query->execute();
    }

    public static function checkCredentials($login, $password)
    {
        if ($login === self::ADMIN_LOGIN && password_verify($password, self::ADMIN_PASSWORD_HASH)) {
            return true;
        }
        return false;
    }

    /**
     * Basic purifying of global arrays $_POST and $_GET string value.
     * @param $param . POST string parameter from global arrays $_POST and $_GET
     * @return mixed
     */
    function purifyVal($param)
    {
        return htmlspecialchars(trim($param));
    }
}