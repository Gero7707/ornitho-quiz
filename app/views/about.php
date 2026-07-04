<?php
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../views/layout/nav.php';
?>

<main class="main-a-propos">
    <section class="about d-flex flex-column mb-5" aria-labelledby="about-title">
        <h1 id="about-title" class="text-center">À propos</h1>

        <p>Bienvenue sur notre plateforme dédiée à l'ornithologie et à la découverte des oiseaux présents sur le territoire français.</p>

        <p>Ce site a été conçu pour permettre à chacun, du simple curieux au passionné d'ornithologie, d'apprendre à reconnaître les oiseaux grâce à leurs chants et à leurs cris. Vous pourrez tester vos connaissances grâce à différents quiz, suivre votre progression au fil du temps et consulter vos statistiques personnelles afin de mesurer vos progrès.</p>

        <p>Notre base de données recense une grande partie des espèces observables en France métropolitaine, dans les départements d'outre-mer et les territoires d'outre-mer. Bien qu'elle soit régulièrement enrichie, elle n'a pas vocation à être exhaustive.</p>

        <p>Chaque fiche espèce rassemble des informations essentielles pour faciliter son identification et approfondir vos connaissances. Vous y retrouverez notamment son statut de conservation lorsqu'il est défini par l'UICN, ainsi que des liens vers plusieurs références reconnues comme Oiseaux.net, GBIF, Wikipédia (français et anglais), Google et YouTube afin d'accéder facilement à des informations complémentaires, des photographies, des vidéos et des observations naturalistes.</p>

        <p>Le site intègre également la technologie BirdNET, un outil d'identification automatique des oiseaux à partir de leurs vocalisations. Lorsqu'une espèce reconnue par BirdNET possède une fiche sur notre plateforme, un lien direct vous permet de la consulter immédiatement afin d'en apprendre davantage.</p>

        <p>Au-delà de son aspect ludique, ce projet poursuit un objectif pédagogique. Apprendre à reconnaître les oiseaux est une manière de mieux comprendre la richesse de notre biodiversité, mais aussi de prendre conscience des menaces qui pèsent sur de nombreuses espèces. La destruction des habitats naturels, l'intensification des activités humaines, les pollutions et le réchauffement climatique contribuent aujourd'hui au déclin de nombreuses populations d'oiseaux.</p>

        <p>Nous espérons que cette plateforme participera, à son échelle, à sensibiliser chacun à la protection de la nature et à l'importance de préserver les espèces qui nous entourent.</p>

        <p class="about-closing">Parce que mieux connaître les oiseaux, c'est déjà commencer à mieux les protéger.</p>
    </section>
</main>
<?php
require_once __DIR__ . '/layout/footer.php';
?>