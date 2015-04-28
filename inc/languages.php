<?php
/* how to use it:
add your own translations cloning the array
add your own messages in the templates with {"english message"|e} : this will tranlate using the config language.
to use it in a php file, you can use e('message')
/*


$messages=array();


/* French */
$messages['fr']=array(
	// keys are always lower case !
	'older'=>'Plus Anciens',
	'newer'=>'Plus récents',
	'home'=>'Accueil',
	'about'=>'A propos...',
	'login'=>'Connexion',
	'logout'=>'Déconnexion',
	'export'=>'Exporter',
	'rss feed'=>'Flux RSS',
	'atom feed'=>'Flux ATOM',
	'tools'=>'Outils',
    'Admin'=>'Admin',
    'Flux'=>'Flux',
	'add link'=>'Ajouter lien',
	'new note'=>'Ajouter note',
	'tag cloud'=>'Nuage de tags',
    'tag'=>'étiquette',
	'Search'=>'Trouver',
    'search keyword'=>'Rechercher par mots clés',
	'or'=>'ou',
	'filter by tags'=>'Par Etiquettes',
	'filter by tag'=>'Filtrer par étiquette',
	'daily'=>'Quotidien',
	'daily rss feed'=>'Flux RSS du Quotidien',
	'the daily shaarli'=>'Le Petit Shaarli Quotidien',
	'picture wall'=>"Mur d'images",
	'links'=>'liens',
	'kt-shin theme by'=>'Thème KT-Shin par',
	'Features'=>'Javascript',
	'shaare your links'=>'J\'ai découvert et partage avec vous',
	'the personal, minimalist, super-fast, no-database delicious clone. by'=>'Le clone personnel de Delicious, minimaliste, super rapide et sans BDD par ',
	'original theme by'=>'Thème original par',
	'pinterest-like theme by'=>'Thème Pinterest par',
	'password'=>'Mot de passe',
	'stay signed in (do not check on public computers)'=>'Rester connecté <p><small>Ne pas cocher sur les ordinateurs publics</small></p>',
	'all links of one day in a single page.'=>'Tous les liens du jour en une page.',
	'previous day'=>'Jour précédent',
	'next day'=>'Jour suivant',
	'change password'=>'Changer le mot de passe',
	'configure your shaarli'=>'Configurer Shaarli',
	'rename/delete tags'=>'Renommer/effacer des tags',
	'import'=>'Importer',
	'export'=>'Exporter',
	'export public'=>'Exporter les liens publics',
	'export public links only'=>'Exporter seulement les liens publics',
	'export private links only'=>'Exporter seulement les liens privés ',
	'shaare link'=>'Partager via Shaarli',
	'add note to notebook'=>'Ajouter une note dans le carnet',
	'link to my notebook'=>'Lien vers mon carnet de notes',
	'my notebook'=>'mon carnet de notes',
	'import netscape html bookmarks (as exported from firefox, chrome, opera, delicious...)'=>'Importer les favoris HTML netscape (exportés de Firefox, Chrome, Opera, Delicious...)',
	'export netscape html bookmarks (which can be imported in firefox, chrome, opera, delicious...)'=>'Exporter les liens vers une page HTML (qui peut être importée sur Firefox, Chrome, Opera, Delicious...)',
	'drag this link to your bookmarks toolbar (or right-click it and choose bookmark this link....)'=>'Glissez ce bouton vers votre barre de favoris',
    'then click <em>shaare link</em> button in any page you want to share'=>'puis cliquez sur ce favori pour partager une nouvelle page',
	'then click on the bookmark button to add a note'=>'puis cliquez sur ce favori pour ajouter une note',
	'this is the link to your personal notebook'=>'Voici le lien vers votre carnet de notes (glissez-le dans votre barre de favoris)',
	'old password'=>'Ancien mot de passe',
	'new password'=>'Nouveau mot de passe',
	'save password'=>'Changer le mot de passe',
	'nothing found'=>'Aucun résultat',
	'results for'=>'résultats pour',
	'results for tags'=>'résultats pour',
	'page title'=>'Titre de votre Shaarli',
	'timezone'=>'Zone géographique',
	'city'=>'Ville',
	'redirector'=>'Redirecteur',
	'will mask the'=>'masquera le',
	'security'=>'sécurité',
	'disable session cookie hijacking protection (check this if you get disconnected often or if your ip address changes often.)'=>'Désactiver le cookie de protection anti Hijacking (cocher en cas de problèmes de déconnexions à répétition ou si votre adresse IP change souvent)',
	'disable jquery and all heavy javascript (for example: autocomplete in tags. useful for slow computers.)'=>'Désactiver le javascript (utile sur les ordinateurs lents)',
	'new link'=>'Nouveau lien',
	'all new link are private by default'=>'Tous les nouveaux liens sont privés par défaut',
	'save config'=>'Sauver la config',
	'rename tag'=>'Renommer tag',
	'delete tag'=>'Supprimer tag',
	'(case sensitive)'=>'(Sensible à la casse)',
	'import netscape html bookmarks (as exported from firefox/chrome/opera/delicious/diigo...)'=>'Importer les favoris HTML (d\'un fichier exporté de Firefox/Chrome/Opera/delicious...)',
	'import all links as private'=>'Importer tous les liens en mode Privé',
	'overwrite existing links'=>'Ecraser les liens existants',
	'import'=>'Importer',
	'export all'=>'Tout exporter',
	'export private'=>'Exporter les liens privés',
	"picture's url to illustrate the note"=>'Adresse d\'une image pour illustrer la note (fac.)',
	"picture's url to illustrate the link"=>'Adresse d\'une image pour illustrer le lien (fac.)',
	'title'=>'Titre',
	"note's content"=>'Contenu de la note',
	'private'=>'Privé',
	'save'=>'Sauver',
	'cancel'=>'Annuler',
	'available'=>'disponible',
	'is'=>'est',
	'you can specify an url for an image or leave blank '=>'Vous pouvez spécifier l\'adresse d\'une image de titre ou laisser vide',
	"image's url"=>'URL d\'une image',
	"wrong token."=>'Mauvais token.',
	"not found."=>'Lien introuvable...',
	"you would mind"=>'Veuillez ',
	"clicking here"=>'cliquez ici',
    "keywords"=>'mots clés',
    'you have been banned from login after too many failed attempts. try later.'=>'Vous avez été banni suite à vos trop nombreuses tentatives de connexions infructueuses. Veuillez réessayer plus tard.',

	// js messages
	'wrong login/password'=>'Mauvais login/mot de passe',
	'are you sure you want to delete this link'=>'Voulez-vous vraiment effacer ce lien',
	'your password has been changed'=>'Votre mot de passe a bien été modifié',
	'configuration was saved'=>'La configuration  a bien été modifiée',
	'tag was removed from '=>'Le tag a été supprimé de ',
	'tag was renamed in '=>'Le tag a été renommé dans ',
	'please upload in smaller chunks'=>'Veuillez le réduire en plusieurs parties',
	'the file you are trying to upload is probably bigger than what this webserver can accept'=>'Le fichier que vous voulez envoyer dépasse la limite',
	'do you really want to remove this tag ?'=>'Voulez-vous VRAIMENT supprimer ce tag ?',
	'are you sure you want to delete this tag from all links'=>'Voulez-vous vraiment supprimer ce tag dans tous les liens',
	'click to close'=>'Cliquer pour fermer',

	'<li>Shaarli is an app from Sebsauvage</li><ul>Shinterest is a Shaarli fork with some changes:   <li>Pinterest like</li>    <li>make & manage notes, todolist with the notebook system</li>    <li>use markdown in descriptions</li>    <li>use infinite scroll instead of pagination</li>    <li>delete tags form the tag cloud (admin)</li>    <li>translate shinterest adding your own transaltions in languages.php  (french/english for now)</li>    <li>add an image as a link illustration</li>    </ul>    <p>This version of Shaarli is clearly not for old computers an browsers.</p>'=>"<li>Shaarli est une application de Sebsauvage</li><li>Shinterest est un fork de Shaarli proposant certaines modifications:   <li>apparence de Pinterest</li>    <li>gérez vos notes, todolist grâce à un système de bloc-notes</li>    <li>utilisez le markdown pour modifier la typo des descriptions</li>    <li>explorez les liens en défilant simplement vers le bas grâce à un infinite scroll</li>    <li>supprimez vos tags depuis le nuage de tags (mode admin)</li>    <li>traduisez shinterest en ajoutant vos traductions dans la page languages.php  (français/anglais pour le moment)</li>    <li>ajoutez une image pour illustrer les liens (utile pour pouvoir poster un site proposant une image tout en ayant cette image visible dans Shaarli)</li>    </li>    <p>Cette version de Shaarli est clairement incompatible avec des machines ou des navigateurs anciens.</p>",
);


/* Basic language function */
function e($message,$echo=true){
	$key=strtolower($message);
	global $messages;
	if (isset($messages[LANG][$key])){$message=$messages[LANG][$key];}
	if ($echo){echo $message;}else{return $message;}
}

?>