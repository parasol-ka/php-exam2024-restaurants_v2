<?php
    // Dans cette partie du code les requetes SQL sont préparées et executée

    include "_connexionBD.php";

    $reqTopRestaurants=$bd->prepare("SELECT r.id_restaurant, r.nom, r.description, COUNT(CASE WHEN e.manager=0 THEN 1 END) AS employes, COUNT(CASE WHEN e.manager=1 THEN 1 END) AS managers, v.ville, p.code FROM restaurants AS r JOIN employes AS e ON r.id_restaurant=e.id_restaurant JOIN villes AS v ON r.id_ville=v.id_ville JOIN pays AS p ON v.code_pays=p.code WHERE e.travail_encore=1 AND r.ouvert=1 GROUP BY r.id_restaurant ORDER BY r.nom LIMIT 10;");
    $reqTopRestaurants->execute();

    $reqRestaurants=$bd->prepare("SELECT r.id_restaurant, r.nom FROM restaurants AS r JOIN employes AS e ON r.id_restaurant=e.id_restaurant WHERE e.travail_encore=1 AND e.manager=0 GROUP BY r.id_restaurant HAVING COUNT(e.id_restaurant)>0 ORDER BY r.nom;");
    $reqRestaurants->execute();

    // Verification de GET à l'aide de trandofrmation en int et en recherchant l'id dans la bd

    if(isset($_GET["resto"])){
        $id_resto_cleaned=(int)$_GET["resto"];
        if($id_resto_cleaned!=NULL){
            $id_restaurant=$id_resto_cleaned;

            $verifyRestaurant=$bd->prepare("SELECT * FROM restaurants WHERE id_restaurant=:id_resto");
            $verifyRestaurant->bindvalue("id_resto", $id_restaurant);
            $verifyRestaurant->execute();
            $resto=$verifyRestaurant->fetch();

            if($resto){
                $reqEmployes=$bd->prepare("SELECT CONCAT(e.nom, ' - ', e.prenom) AS nom_prenom, r.id_restaurant, e.manager, SUM(b.prix*v.nombre) AS montant 
                                        FROM restaurants AS r JOIN employes AS e ON r.id_restaurant=e.id_restaurant 
                                        JOIN commandes AS c ON e.id_employe=c.id_employe 
                                        JOIN ventes AS v ON c.id_commande=v.id_commande 
                                        JOIN burgers AS b ON v.id_burger=b.id_burger 
                                        WHERE e.travail_encore=1 AND r.id_restaurant=:id_resto 
                                        GROUP BY e.id_employe");

                $reqEmployes->bindvalue("id_resto", $id_restaurant);
                $reqEmployes->execute();

                $reqTotal=$bd->prepare("SELECT sousrequete.nom, ROUND(SUM(sousrequete.montant)) AS total FROM 
                                    (SELECT CONCAT(e.nom, ' - ', e.prenom) AS nom_prenom, r.id_restaurant, e.manager, SUM(b.prix*v.nombre) AS montant, r.nom FROM restaurants AS r 
                                    JOIN employes AS e ON r.id_restaurant=e.id_restaurant 
                                    JOIN commandes AS c ON e.id_employe=c.id_employe 
                                    JOIN ventes AS v ON c.id_commande=v.id_commande 
                                    JOIN burgers AS b ON v.id_burger=b.id_burger 
                                    WHERE e.travail_encore=1 AND r.id_restaurant=:id_resto 
                                    GROUP BY e.id_employe) AS sousrequete;");
                $reqTotal->bindvalue("id_resto", $id_restaurant);
                $reqTotal->execute();
                
                
            }else {header("Location:index.php"); $id_restaurant=NULL;}

        }else {header("Location:index.php"); $id_restaurant=NULL;}
    }


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>RestaurantsV2</title>
</head>
<body>
    <main>
        <div id="topresto_employes_container">
            <?php
                $line_nbr=1;
                while($topRestaurants=$reqTopRestaurants->fetch()){
                    $id_resto=$topRestaurants["id_restaurant"];
                    $resto_name=$topRestaurants["nom"];
                    $resto_description=$topRestaurants["description"];
                    $employes_nbr=$topRestaurants["employes"];
                    $managers_nbr=$topRestaurants["managers"];
                    $town_name=$topRestaurants["ville"];
                    $country_code=$topRestaurants["code"];

                    if($line_nbr<4){$bold_style="style='font-weight: bold;'";}else{$bold_style="";}


                    echo "<div id='top_restaurant_line_container'>$line_nbr : 
                        <img src='flags/$country_code.webp'> $town_name : <a href='index.php?resto=$id_resto#resto_employes_container' class='resto_links' $bold_style>$resto_name</a><br>
                        $resto_description<br>";
                        if($managers_nbr>0){
                            for ($i=0; $i < $managers_nbr; $i++) { 
                                echo "<img src='icones/manager.png' class='employes_icons'>";
                        }}

                        if($employes_nbr>0){
                            for ($i=0; $i < $employes_nbr; $i++) { 
                                echo "<img src='icones/employe.png' class='employes_icons'>";
                        }}
                        echo "</div>";
                    $line_nbr++;}?>
        </div>
        <div id="resto_form_container">
            <form action="index.php">
                <select name="resto" id="resto">
                    <?php
                        while($restaurants=$reqRestaurants->fetch()){
                                $id_resto_select=$restaurants["id_restaurant"];
                                $resto_select_name=$restaurants["nom"];

                                if($id_resto_select==$id_restaurant){$selected="selected";}else{$selected="";}

                                echo "<option value='$id_resto_select' $selected>$resto_select_name</option>";
                        }
                    ?>
                </select>
                <input type="submit" value="Voir les employés">
            </form>
            <?php
                if(isset($id_restaurant)){
                    echo "<div id='resto_employes_container'>";

                    if($reqEmployes->rowCount() > 0){
                        while ($employes=$reqEmployes->fetch()){
                            $employe_name=$employes["nom_prenom"];
                            $manager_bool=$employes["manager"];
                            $total_per_employe=$employes["montant"];
                            if($manager_bool){$bold_style_manager="style='font-weight: bold;'";}else{$bold_style_manager="";}


                            echo "<div class='employes'><p $bold_style_manager>$employe_name</p><p>$total_per_employe €</p></div>";
                        }

                        $total=$reqTotal->fetch();

                        if($total){
                            $total_resto=$total["total"];
                            $resto_name_total=$total["nom"];
            
                            echo "<p>Le total des ventes pour $resto_name_total est de : $total_resto €</p>";
                        }
                    }else {echo"Il n'y a pas d'employés dans ce restaurant";}
                    

                    echo "</div>";
                }
            ?>
        </div>
    </main>
</body>
</html>