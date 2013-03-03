<?PHP

/*
 * Copyright (C) 2013 Joachim Barthel
 * 
 * Version: 0.1 - Export of Orders as XML
 * 
 * Parameters:
 * limt: Number of Orders to transfer (starting from the newest)
 * year: All orders of the specified year
 * 
 */

class FakturamaExport {

    //var $aLang;
    public $limit = 0;
    public $year = '%';
	
    function openXML()
    {
        include '../../config.inc.php';

        header("Content-Type: text/plain; charset=utf-8" );
        
        $this->expLine( '<?xml version="1.0" encoding="UTF-8"?><webshopexport version="1.0" >' );
        
        $this->expLine( '<webshop shop="OXID eShop" url="'.$this->sShopURL.'/modules/fakturama"></webshop>' );
    }
	
	
    function closeXML()
    {
        $this->expLine( '</webshopexport>' );
    }


    function exportArticles()
    {
        
    }


    function exportOrders($dbh)
    {
        $orders = $this->getOrders($dbh);
        
        $this->expLine( '<orders>' );

        foreach( array_reverse($orders) as $order ) {
            $this->expLine( '<order id="'.$order['oxordernr'].'" date="'.$order['oxorderdate'].'" currency="EUR" currency_value="1.000000" status="'.$order['orderstatus'].'" >' );
            $this->expLine( '<contact id="'.$order['custnr'].'" >' );
            $this->expLine( '<gender>'.$order['billsal'].'</gender>' );
            $this->expLine( '<firstname>'.$this->convText($order['oxbillfname']).'</firstname>' );
            $this->expLine( '<lastname>'.$this->convText($order['oxbilllname']).'</lastname>' );
            $this->expLine( '<company>'.$this->convText($order['oxbillcompany']).'</company>' );
            $this->expLine( '<street>'.$this->convText($order['billstreet']).'</street>' );
            $this->expLine( '<zip>'.$order['oxbillzip'].'</zip>' );
            $this->expLine( '<city>'.$this->convText($order['oxbillcity']).'</city>' );
            $this->expLine( '<country>'.$this->convText($order['billcountry']).'</country>' );
            $this->expLine( '<delivery_gender>'.$order['delsal'].'</delivery_gender>' );
            $this->expLine( '<delivery_firstname>'.$this->convText($order['delfname']).'</delivery_firstname>' );
            $this->expLine( '<delivery_lastname>'.$this->convText($order['dellname']).'</delivery_lastname>' );
            $this->expLine( '<delivery_company>'.$this->convText($order['delcompany']).'</delivery_company>' );
            $this->expLine( '<delivery_street>'.$this->convText($order['delstreet']).'</delivery_street>' );
            $this->expLine( '<delivery_zip>'.$order['delzip'].'</delivery_zip>' );
            $this->expLine( '<delivery_city>'.$this->convText($order['delcity']).'</delivery_city>' );
            $this->expLine( '<delivery_country>'.$this->convText($order['delcountry']).'</delivery_country>' );
            $this->expLine( '<phone>'.$order['oxbillfon'].'</phone>' );
            $this->expLine( '<email>'.$order['oxbillemail'].'</email>' );
            $this->expLine( '</contact>' );
            
            $orderArticles = $this->getOrderArticles($dbh, $order['oxid']);
            foreach($orderArticles as $article) {
                $this->expLine( '<item id="'.$article['oxartnum'].'" quantity="'.$article['oxamount'].'" gross="'.$article['oxprice'].'" vatpercent="'.$article['oxvat'].'">' );
                $this->expLine( '<model>'.$article['oxartnum'].'</model>' );
                $this->expLine( '<name>'.$this->convText($article['title']).'</name>' );
                $this->expLine( '<category>aa</category>' );
                $this->expLine( '<vatname></vatname>' );
                //$this->expLine( '<short_description>'.$article['oxshortdesc'].'</short_description>' );
                $this->expLine( '</item>' );
            }
            
            $this->expLine( '<shipping gross="'.$order['oxdelcost'].'" vatpercent="'.$order['oxdelvat'].'" >' );
            $this->expLine( '<name>'.$this->convText($order['deltype']).'</name>' );
            $this->expLine( '<vatname>MwSt. '.$order['oxdelvat'].'%</vatname>' );
            $this->expLine( '</shipping>' );
            
            $this->expLine( '<payment type="'.$order['oxpaymenttype'].'" total="'.$order['oxtotalordersum'].'">' );
            $this->expLine( '<name>'.$this->convText($order['paymentname']).'</name>' );
            $this->expLine( '</payment>' );
            $this->expLine( '</order>' );
        }
        
        $this->expLine( '</orders>' );
    }
    
    
    function openDB()
    {
        include '../../config.inc.php';
        //include 'config.inc.php';

        $dbConn = 'mysql:host='.$this->dbHost.';port=3306;dbname='.$this->dbName;
        $dbUser = $this->dbUser;
        $dbPass = $this->dbPwd;

        $dbh = new PDO($dbConn, $dbUser, $dbPass); 
        //$dbh->exec('set names "utf8"');

        if (!empty($dbh)) 
            return $dbh;
        else
            return 0;
    }
    
    
    function closeDB($dbh)
    {
        $dbh = null;
    }
    
    
    function getOrders($dbh)
    {
        $sql = "SELECT " 
                    . "o.oxid, o.oxordernr, o.oxorderdate, (SELECT u.oxcustnr FROM oxuser u WHERE o.oxuserid=u.oxid) AS custnr, "
                    . "IF (o.oxbillsal='MR', 'm', 'f') AS billsal, o.oxbillfname, o.oxbilllname, o.oxbillcompany, "
                    . "CONCAT(o.oxbillstreet, ' ', o.oxbillstreetnr) AS billstreet, o.oxbillzip, o.oxbillcity, "
                    . "(SELECT c.oxtitle FROM oxcountry c WHERE o.oxbillcountryid=c.oxid) as billcountry, "
                    . "IF (o.oxbillsal='MR', 'm', 'f') AS delsal, IF (o.oxdelfname='', o.oxbillfname, o.oxdelfname) AS delfname, "
                    . "IF (o.oxdellname='', o.oxbilllname, o.oxdellname) AS dellname, IF (o.oxdelcompany='', o.oxbillcompany, o.oxdelcompany) AS delcompany, "
                    . "IF (o.oxdelstreet='', CONCAT(o.oxbillstreet, ' ', o.oxbillstreetnr), CONCAT(o.oxdelstreet, ' ', o.oxdelstreetnr)) AS delstreet, "
                    . "IF (o.oxdelzip='', o.oxbillzip, o.oxdelzip) AS delzip, IF (o.oxdelcity='', o.oxbillcity, o.oxdelcity) AS delcity, "
                    . "IF (o.oxdelcountryid='', o.oxbillcountryid, o.oxdelcountryid) AS delcountryid, "
                    . "IF (o.oxdelcountryid='', "
                        . "(SELECT c.oxtitle FROM oxcountry c WHERE o.oxbillcountryid=c.oxid), "
                        . "(SELECT c.oxtitle FROM oxcountry c WHERE o.oxdelcountryid=c.oxid)) "
                        . "AS delcountry, "
                    . "o.oxbillfon, o.oxbillemail, "
                    . "IF(o.oxpaid!='0000-00-00 00:00:00', IF(o.oxsenddate='0000-00-00 00:00:00', 'pending', 'shipped'), IF(o.oxsenddate='0000-00-00 00:00:00', 'processing', 'completed')) AS orderstatus, "
                    . "(SELECT d.oxtitle FROM oxdeliveryset d WHERE o.oxdeltype=d.oxid) AS deltype, o.oxdelcost, o.oxdelvat, "
                    . "o.oxpaymenttype, (SELECT p.oxdesc FROM oxpayments p WHERE o.oxpaymenttype=p.oxid) AS paymentname, o.oxtotalordersum "
                . "FROM oxorder o "
                . "WHERE o.oxstorno = 0 "
                    . "AND YEAR(o.oxorderdate) LIKE '$this->year' "
                . "ORDER BY o.oxorderdate DESC "
                . "LIMIT 0, $this->limit ";
        //echo $sql;
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $dbData;
    }
    
    
    function getOrderArticles($dbh, $oxid)
    {
        $sql = "SELECT oxartnum, oxamount, oxprice, oxvat, CONCAT(oxtitle, IF(oxselvariant='', '', ', '), oxselvariant) AS title, oxshortdesc "
                . "FROM oxorderarticles "
                . "WHERE oxorderid = '$oxid' "
                    . "AND oxstorno = 0 ";
                //. "LIMIT 0, 3";
        //echo '<hr>'.$sql.'<hr>';
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $dbData;
    }

    
    function convText($text)
    {

        $text = htmlspecialchars( $text );
        $text = utf8_encode( $text );
        
        return $text;
    }
    
    
    function expLine($text)
    {
        echo $text . "\n";
        return;
    }

	
}


// ============================================================================================


$exp = new FakturamaExport;

$exp->limit = intval( isset($_POST['limit']) ? $_POST['limit'] : $_GET['limit'] );
$exp->year = intval( isset($_POST['year']) ? $_POST['year'] : $_GET['year'] );
if (($exp->limit == 0) && ($exp->year == 0)) {
    $exp->limit = 10;
    $exp->year = date("z");
} 
elseif ($exp->limit != 0) {
    $exp->year = '%';
} 
else {  // $exp->year != 0
    $exp->limit = 1000;
}

$exp->openXML();
$dbh = $exp->openDB();
$exp->exportOrders($dbh);
$exp->closeDB($dbh);
$exp->closeXML();

?>