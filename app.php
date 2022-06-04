<?php

    include("db.php");
    session_start();

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['uname'])) {
        echo json_encode(array(
            "code" => 404,
            "msg" => "user not found"
        ));

        exit(0);
    } else {
        $uname = $_SESSION['uname'];
        $uid = $_SESSION['uid'];
    }

    if (!isset($_POST['q'])) {
        echo json_encode(array(
            "403" => 200,
            "msg" => "empty request"
        ));
        exit(0);
    }

    $q = json_decode($_POST['q'], true);


    function isConvMember($convId) {
        $conn = $GLOBALS['conn'];
        $uid = $GLOBALS['uid'];
        $req = $conn->prepare("select cid from conv where cid=? and uid=?");
        $req->execute(array($convId, $uid));
        return ($req->fetch(PDO::FETCH_ASSOC));
    }

    function sendMessage($args) {
        if (!isset($args['body']) || !isset($args['convId'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $body = $args['body'];
        $convId = $args['convId'];
        if (!isConvMember($convId)) return array("code" => 403, "msg" => "you are not a member of this chat.");
        $conn = $GLOBALS['conn'];
        $uid = $GLOBALS['uid'];
        $uname = $GLOBALS['uname'];
        $req = $conn->prepare("insert into message (sid, cid, body, sname) values (?,?,?,?)");
        if ($req->execute(array($uid, $convId, $body, $uname))) {
            return array(
                "code" => 200,
                "msg" => "message sent successfully"
            );
        }
        return array(
            "code" => 503,
            "msg" => "internal error"
        );
    }

    function getConvMessages($args) {
        if (!isset($args['convId']) || !isset($args['offset']) || !isset($args['limit'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $convId = $args['convId'];
        $offset = $args['offset'];
        $limit = $args['limit'];
        if (!isConvMember($convId)) return array("code" => 403, "msg" => "you are not a member of this chat.");
        $conn = $GLOBALS['conn'];
        $uid = $GLOBALS['uid'];
        if ($offset != -1)
            $req = $conn->prepare("select mid, sid, body, ts, sname from message where cid=:cid and mid <= :mid order by mid desc limit :limit");
        else
        $req = $conn->prepare("select mid, sid, body, ts, sname from message where cid=:cid order by mid desc limit :limit");
        $req->bindValue(':cid', $convId, PDO::PARAM_INT);
        if ($offset != -1) $req->bindValue(':mid', $offset, PDO::PARAM_INT);
        $req->bindValue(':limit', $limit, PDO::PARAM_INT);
        $req->execute();
        $res = $req->fetchAll(PDO::FETCH_ASSOC);
        if ($res) {
            return array(
                "code" => 200,
                "msg" => "success",
                "uid" => $uid,
                "messages" => $res
            );
        }
        return array(
            "code" => 503,
            "msg" => "empty chat"
        );
    }

    function getConvMessagesCount($args) {
        if (!isset($args['convId'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $convId = $args['convId'];
        if (!isConvMember($convId)) return array("code" => 403, "msg" => "you are not a member of this chat.");
        $conn = $GLOBALS['conn'];
        $req = $conn->prepare("select count(*) from message where cid=?");
        $req->execute(array($convId));
        $N = $req->fetch(PDO::FETCH_NUM);
        if ($N) {
            return array(
                "code" => 200,
                "count" => $N[0]
            );
        }
        return array(
            "code" => 404,
            "msg" => "chat not found"
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

    function quitChat($args) {
        if (!isset($args['chatId'])) {
            return array(
                "code" => 403,
                "msg" => "missing required parameters"
            );
        }
        $conn = $GLOBALS['conn'];
        $name = $_SESSION['uname'];
        $uid = $_SESSION['uid'];
        $chatId = $args['chatId'];
        $req = $conn->prepare("insert into message (sid, cid, body) values (?,?,?)");
        $req->execute(array(0, $chatId, "@" . $name . " left."));
        $req = $conn->prepare("update conversation set nb_members = nb_members - 1 where cid = ?");
        $req->execute(array($chatId));
        $req = $conn->prepare("delete from conv where cid=? and uid=?");
        $req->execute(array($chatId, $uid));
        $req = $conn->prepare("delete from member where id=?");
        $req->execute(array($uid));
        unset($_SESSION['uname']);
        unset($_SESSION['uid']);
        session_destroy();
        return array(
            "code" => 200,
            "msg" => "OK"
        );
    }

    $methods = array(
        "sendMessage" => 0,
        "getConvMessages" => 1,
        "getConvMessagesCount" => 2,
        "getChatInfo" => 3,
        "quitChat" => 4
    );

    // var_dump($q);

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

    // if (sendMessage('Hi there', 1337)) echo "sent successfully";
    // isConvMember(1337);

    // getConvMessages(1337, 3);
    // var_dump(getConvMessagesCount(1338));



?>