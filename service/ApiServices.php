<?php
require_once("../core/Database.php");
session_start();
$db = new Database();
$Request = @$_REQUEST['req'];

function createKodeUser(){
    $kode = "USR".date("dHis");
    return $kode;
}

function createKodeTransaksi($tipe){
    $kode = "TRX".$tipe.random_int(1000,9999);
    return $kode;
}

if ($Request == "daftar_barang") {
    $getData = $db->select("SELECT * FROM daftar_barang");
}
elseif ($Request == "login") {
    $username = $_POST['username'];
    $auth = $db->select("SELECT * FROM user_login WHERE username = '". mysqli_real_escape_string($db->koneksi,$username)."'");
    if (count($auth) > 0) {
        $password = password_verify($_POST['password'],$auth[0]['password']);
        if ($password) {
            $tokenID = md5($username.random_int(1,999));
            // $db->update("user_login",["token"=>$tokenID],$auth[0]['id']);
            $_SESSION["token"] = $tokenID;
            $_SESSION["username"] = $username;
            $_SESSION["nama"] = $auth[0]['nama'];
            $_SESSION["kode_user"] = $auth[0]['kode_user'];
            header("location: ../dashboard.php");
        }else {
            setcookie("alert","Password Salah !", time() + (15),"/");
            header("location: ../index.php");
        }
    }else {
        setcookie("alert","Username Tidak Ditemukan", time() + (15),"/");
        header("location: ../index.php");
    }
}
elseif ($Request == "register") {
    $nama = $_POST['nama'];
    $username = $_POST["username"];
    $password = $_POST["password"];
    $email = $_POST['email'];
    $kode_user = createKodeUser();
    $data = [
        "kode_user" => $kode_user,
        "nama" => $nama,
        "username" => $username,
        "password" => password_hash($password,PASSWORD_BCRYPT),
        "email" => $email
    ];
    $db->insert("user_login",$data);
    $_SESSION["username"] = $username;
    $_SESSION["nama"] = $nama;
    $_SESSION["kode_user"] = $kode_user;
    header("location: ../dashboard.php");
}
elseif ($Request == "tambah_barang") {
    $data = [
        "kode_user" => $_SESSION['kode_user'],
        "nama_barang" => $_POST['nama_barang'],
        "jumlah" => $_POST['jumlah'],
        "kode_satuan" => $_POST['kode_satuan'],
        "tanggal_restock" => date("Y-m-d"),
        "harga_beli" => $_POST['harga_beli'],
        "harga_jual" => $_POST['harga_jual']
    ];
    return $db->insert("daftar_barang",$data);
}
elseif ($Request == "tambah_satuan") {
    $data = [
        "kode_user" => $_SESSION['kode_user'],
        "nama_satuan" => $_POST['nama_satuan']
    ];
    return $db->insert("daftar_satuan",$data);
}
elseif ($Request == "transaksi_pembelian") {
    $kode_transaksi = createKodeTransaksi("BELI");
    $dataTransaksi = [
        "kode_user" => $_SESSION['kode_user'],
        "kode_transaksi" => $kode_transaksi,
        "tanggal" => date("Y-m-d"),
        "total_pembelian" => $_POST['grand_total'],
        "total_bayar" => $_POST['total_bayar'],
        "total_kurang" => $_POST['total_kurang'],
        "catatan" => "kosong"
    ];
    $result = $db->insert("transaksi_pembelian", $dataTransaksi);
    foreach ($_POST['id_barang'] as $key => $val) {
        if ($_POST["id_barang"][$key] == "undefined") {
            $namaBarang = $_POST["nama_barang"];
            $dataBarang = [
                "kode_user" => $_SESSION["kode_user"],
                "nama_barang" => $_POST["nama_barang"][$key],
                "jumlah" => $_POST["jumlah_barang"][$key],
                "kode_satuan" => $_POST["satuan"][$key],
                "tanggal_restock" => date("Y-m-d"),
                "harga_beli" => $_POST["harga_barang"][$key],
                "harga_jual" => $_POST["total_jual"][$key]
            ];
            $db->insert("daftar_barang",$dataBarang);
            $getID = $db->select("SELECT id FROM daftar_barang WHERE nama_barang LIKE '%$namaBarang%'");
            
            $dataDetailTransaksi = [
                "kode_user" => $_SESSION["kode_user"],
                "kode_transaksi" => $kode_transaksi,
                "id_barang" => $getID,
                "harga_beli" => $_POST["harga_beli"][$key],
                "jumlah_barang" => $_POST["jumlah_barang"][$key],
                "subtotal" => $_POST["subtotal"][$key],
                "kode_satuan" => $_POST["satuan"][$key]
            ];
            $insertDetail = $db->insert("detail_transaksi_pembelian", $dataDetailTransaksi);
        }else{
            $dataDetailTransaksi = [
                "kode_user" => $_SESSION["kode_user"],
                "kode_transaksi" => $kode_transaksi,
                "id_barang" => $_POST["id_barang"][$key],
                "harga_beli" => $_POST["harga_barang"][$key],
                "jumlah_barang" => $_POST["jumlah_barang"][$key],
                "subtotal" => $_POST["subtotal"][$key],
                "kode_satuan" => $_POST["satuan"][$key]
            ];
            $insertDetail = $db->insert("detail_transaksi_pembelian", $dataDetailTransaksi);
            $cek = $db->query("CALL SyncPembelian('".@$_POST["id_barang"][$key]."', '".@$_SESSION["kode_user"]."', '".date("Y-m-d")."', '".@$_POST["jumlah_barang"][$key]."', '".@$_POST["harga_barang"][$key]."',@a)");
        }
    }
    header("location:../arusmodal.php");
}
elseif ($Request == "transaksi_penjualan") {
    $kode_transaksi = createKodeTransaksi("JUAL");
    $dataTransaksi = [
        "kode_user" => $_SESSION['kode_user'],
        "kode_transaksi" => $kode_transaksi,
        "tanggal" => date("Y-m-d"),
        "total_harga" => $_POST['grand_total'],
        "total_bayar" => $_POST['total_bayar'],
        "total_kurang" => $_POST['total_kurang'],
        "catatan" => "Kosong"
    ];
    $cek = $db->insert("transaksi_penjualan",$dataTransaksi);
    var_dump($cek);

    foreach ($_POST['id_barang'] as $key => $val) {
        $dataDetailTransaksi = [
            "kode_user" => $_SESSION["kode_user"],
            "kode_transaksi" => $kode_transaksi,
            "id_barang" => $_POST["id_barang"][$key],
            "harga_barang" => $_POST["harga_barang"][$key],
            "jumlah_barang" => $_POST["jumlah_barang"][$key],
            "subtotal" => $_POST["subtotal"][$key],
            "kode_satuan" => $_POST["satuan"][$key]
        ];
        $insertDetail = $db->insert("detail_transaksi_penjualan", $dataDetailTransaksi);
        $cek = $db->query("CALL SyncPenjualan(@a,@b,'".@$_POST["id_barang"][$key]."', '".@$_SESSION["kode_user"]."', '".@$_POST["jumlah_barang"][$key]."')");
    }
    header("location: ../arusmodal.php");
}
elseif ($Request == "hapus") {
    $id = $_GET['id'];
    $code = $_GET['code'];
    $cek = substr($code,3,4);
    $table = "";
    if ($cek == "BELI") {
        $table = "transaksi_pembelian";
    }else{
        $table = "transaksi_penjualan";
    }
    $db->delete($table,['id'=>$id]);
    header("location: ../arusmodal.php");
}
elseif($Request == "logout"){
    session_destroy();
    header("location:../index.php");
}
