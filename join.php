<?php

    include("db.php");
    session_start();

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_POST['q'])) {
        echo json_encode(array(
            "403" => 200,
            "msg" => "empty request"
        ));
        exit(0);
    }

    $q = json_decode($_POST['q'], true);

    function memberExists($id) {
        $conn = $GLOBALS['conn'];
        $req = $conn->prepare("select id from member where id=?");
        $req->execute(array($id));
        return ($req->fetch(PDO::FETCH_ASSOC));
    }

    function chatExists($chatId) {
        $conn = $GLOBALS['conn'];
        $req = $conn->prepare("select cid from conversation where cid=?");
        $req->execute(array($chatId));
        return ($req->fetch(PDO::FETCH_ASSOC));
    }

    function createMember($name) {
        $conn = $GLOBALS['conn'];
        $id = 0;
        for ($i=0; $i < 1337; $i++) {
            $id = rand(1000000, 10000000);
            if (!memberExists($id)) break;
        }
        if ($i == 1337) {
            return -1;
        }
        $req = $conn->prepare("insert into member (id, name) values (?, ?)");
        if (!$req->execute(array($id, $name))) {
            return -1;
        }
        return $id;
    }

    function joinChat($args) {
        if (!isset($args['chatId']) || !isset($args['name']) || empty($args['name'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $conn = $GLOBALS['conn'];
        $name = str_replace(" ", "_", $args['name']);
        $chatId = $args['chatId'];
        if (!chatExists($chatId)) {
            return array(
                "code" => 404,
                "msg" => "chat does not exist"
            );
        }
        $id = createMember($name);
        if ($id < 0) {
            return array(
                "code" => 404,
                "msg" => "failed to create new member"
            );
        }
        $req = $conn->prepare("insert into conv (cid, uid) values (?,?)");
        if ($req->execute(array($chatId, $id))) {
            $req = $conn->prepare("update conversation set nb_members = nb_members + 1 where cid = ?");
            $req->execute(array($chatId));
            $req = $conn->prepare("insert into message (sid, cid, body) values (?,?,?)");
            $req->execute(array(0, $chatId, "@" . $name . " has joined the chat."));
            $_SESSION['uid'] = $id;
            $_SESSION['uname'] = $name;
            return array(
                "code" => 200,
                "msg" => "joined successfully",
                "chatId" => $chatId,
                "uid" => $id
            );
        }
        return array(
            "code" => 404,
            "msg" => "failed to join the chat"
        );
    }

    function createChat($args) {
        if (!isset($args['name']) || !isset($args['cname']) || empty($args['cname'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $conn = $GLOBALS['conn'];
        $name = str_replace(" ", "_", $args['name']);
        $cname = str_replace(" ", "_", $args['cname']);
        $chatId = 0;
        for ($i=0; $i < 1337; $i++) {
            $chatId = rand(1000000, 10000000);
            if (!chatExists($chatId)) break;
        }
        if ($i == 1337) {
            return array(
                "code" => 404,
                "msg" => "failed to create new chat"
            );
        }
        $id = createMember($name);
        if ($id < 0) {
            return array(
                "code" => 404,
                "msg" => "failed to create new member"
            );
        }
        $req = $conn->prepare("insert into conversation (cid, name, created_by) values (?,?,?)");
        if ($req->execute(array($chatId, $cname, $name))) {
            $req = $conn->prepare("insert into conv (cid, uid) values (?,?)");
            if ($req->execute(array($chatId, $id))) {
                $req = $conn->prepare("insert into message (sid, cid, body) values (?,?,?)");
                $req->execute(array(0, $chatId, "@" . $name . " created this chat."));
                $_SESSION['uid'] = $id;
                $_SESSION['uname'] = $name;
                return array(
                    "code" => 200,
                    "msg" => "chat created successfully",
                    "chatId" => $chatId,
                    "name" => $cname
                );
            }
            return array(
                "code" => 404,
                "msg" => "failed to init the chat"
            );
        }
        return array(
            "code" => 404,
            "msg" => "failed to create new chat"
        );
    }

    function getChatInfo($args) {
        if (!isset($args['chatId'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $conn = $GLOBALS['conn'];
        $chatId = $args['chatId'];
        $req = $conn->prepare("select * from conversation where cid=?");
        $req->execute(array($chatId));
        $chat = $req->fetch(PDO::FETCH_ASSOC);
        if ($chat) {
            return array(
                "code" => 200,
                "chat" => $chat
            );
        }
        return array(
            "code" => 404,
            "msg" => "chat not found"
        );
    }

    $methods = array(
        "joinChat" => 0,
        "createChat" => 1,
        "getChatInfo" => 2
    );

    $method = $q['method'];


    if (!array_key_exists($method, $methods)) {
        echo json_encode(array(
            "code" => 404,
            "msg" => "method not found"
        ));
        exit(0);
    }

    echo json_encode(
        call_user_func($method, $q['args'])
    );
?>