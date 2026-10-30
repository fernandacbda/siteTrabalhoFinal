<?php
declare(strict_types=1);

// Dados fictícios para demonstração. São inseridos uma única vez em cada banco:
// se o usuário de demonstração já existir, nada é feito.
const DEMO_EMAIL = 'demo@menureview.com';
const DEMO_PASSWORD = 'demo1234';

// Bancos criados antes de existirem essas imagens receberam os artigos sem capa.
function addDemoCovers(PDO $database): void
{
    $covers = [
        ['Hambúrguer artesanal sem frescura', 'rafael@nomesite.com', '../img/hamburguer.png'],
        ['Feijoada de sábado com samba ao vivo', 'mariana@nomesite.com', '../img/feijoada.png'],
    ];
    $statement = $database->prepare(
        'UPDATE articles SET cover_image = :cover
         WHERE title = :title AND cover_image IS NULL
           AND author_id = (SELECT id FROM users WHERE email = :email)'
    );
    foreach ($covers as [$title, $email, $cover]) {
        $statement->execute([':cover' => $cover, ':title' => $title, ':email' => $email]);
    }
}

function seedDemoData(PDO $database): void
{
    $exists = $database->prepare('SELECT 1 FROM users WHERE email = :email');
    $exists->execute([':email' => DEMO_EMAIL]);
    if ($exists->fetchColumn()) {
        addDemoCovers($database);
        return;
    }

    $users = [
        'demo' => ['Usuário Demo', DEMO_EMAIL, 'Apaixonado por comida boa e por contar histórias sobre cada refeição.', 60],
        'mariana' => ['Mariana Costa', 'mariana@nomesite.com', 'Confeiteira nas horas vagas e caçadora de cafés especiais.', 55],
        'rafael' => ['Rafael Souza', 'rafael@nomesite.com', 'Engenheiro que sai do trabalho direto para o próximo restaurante.', 50],
        'juliana' => ['Juliana Lima', 'juliana@nomesite.com', 'Fã de culinária japonesa e de qualquer prato com muito molho.', 45],
        'pedro' => ['Pedro Alves', 'pedro@nomesite.com', 'Cozinheiro amador, sempre atrás de uma boa massa caseira.', 40],
    ];

    $articles = [
        'pizza' => [
            'demo', 'A melhor pizza de fermentação natural da cidade',
            'Massa leve, borda aerada e ingredientes frescos: a Pizzaria Forno Antigo merece a fama.',
            "Cheguei à Pizzaria Forno Antigo numa sexta-feira à noite e o salão já estava cheio — sinal de que a fama não é à toa.\n\nA massa é de fermentação natural, descansa por 48 horas e chega à mesa com a borda alta, aerada e levemente tostada pelo forno a lenha. Pedi a margherita clássica e uma de linguiça artesanal com cebola caramelizada. As duas estavam equilibradas, sem excesso de queijo, e dava para sentir o sabor do tomate italiano.\n\nO atendimento foi rápido mesmo com a casa lotada, e o garçom indicou um vinho da casa que combinou muito bem. O preço é um pouco acima da média, mas a qualidade justifica.\n\nVoltaria com certeza. Recomendo chegar antes das 20h para não pegar fila.",
            '../img/pizza.jpg', 'published', 28, 184,
        ],
        'sushi' => [
            'juliana', 'Rodízio japonês que surpreende pela qualidade',
            'O Sakura Sushi Bar foge do rodízio comum com peixes frescos e pratos quentes bem executados.',
            "Sempre desconfio de rodízio japonês, porque costuma priorizar quantidade. O Sakura Sushi Bar foi uma ótima surpresa.\n\nOs peixes são cortados na hora, na frente do cliente, e o salmão tinha aquela textura amanteigada que só peixe fresco tem. Os destaques foram o niguiri de atum maçaricado e o hot roll, que veio sequinho e nada gorduroso.\n\nOs pratos quentes também merecem atenção: o lámen de missô é servido em porção individual e tem um caldo encorpado. Para fechar, o sorvete de chá verde é feito na casa.\n\nO ambiente é tranquilo e bom para conversar. Ponto negativo: o estacionamento é pequeno.",
            '../img/sushi.jpg', 'published', 24, 236,
        ],
        'cafe' => [
            'mariana', 'Um café para passar a tarde inteira',
            'O Grão & Prosa tem cafés especiais, bolos caseiros e um ambiente perfeito para ler ou trabalhar.',
            "Procurava um lugar para trabalhar fora de casa e acabei encontrando meu novo café favorito.\n\nO Grão & Prosa trabalha com grãos de pequenos produtores do sul de Minas, e o barista explica a origem de cada um. Experimentei um coado no V60 com notas de chocolate e caramelo, e um cappuccino com leite vaporizado no ponto certo.\n\nOs bolos são feitos ali mesmo: o de laranja com calda é imperdível, e o pão de queijo sai quentinho a cada meia hora. Tem tomadas em quase todas as mesas e o Wi-Fi funciona bem.\n\nÉ um lugar aconchegante, com música baixa e atendimento simpático. Perfeito para uma tarde de leitura.",
            '../img/cafe.jpg', 'published', 20, 152,
        ],
        'lasanha' => [
            'pedro', 'Lasanha à bolonhesa como a da avó',
            'Na Cantina da Nonna, a lasanha é feita com massa fresca e molho que cozinha por horas.',
            "A Cantina da Nonna é um restaurante pequeno, de família, e isso aparece em cada detalhe.\n\nA lasanha à bolonhesa é o carro-chefe: massa fresca aberta à mão, molho de carne que cozinha lentamente por horas e um bechamel leve que gratina no forno. A porção é generosa e serve bem duas pessoas com fome moderada.\n\nTambém provei o nhoque de batata ao sugo, macio e bem feito. A carta de vinhos é curta, mas tem boas opções nacionais com preço justo.\n\nO único porém é que aos domingos a espera passa de uma hora. Vale reservar.",
            '../img/lasanha.jpg', 'published', 16, 98,
        ],
        'panqueca' => [
            'demo', 'Café da manhã com panquecas americanas',
            'O Brunch Club serve panquecas fofinhas com frutas e maple syrup, ideais para o fim de semana.',
            "Fui ao Brunch Club num sábado de manhã e entendi por que o lugar vive cheio no fim de semana.\n\nAs panquecas americanas chegam em pilha de três, altas e fofinhas, acompanhadas de frutas vermelhas, manteiga e maple syrup de verdade. Também pedi ovos beneditinos, com a gema no ponto e o molho holandês bem temperado.\n\nO suco de laranja é espremido na hora e o café é passado na prensa francesa. A decoração é clara e cheia de plantas, o que deixa o ambiente bem agradável.\n\nPara quem gosta de um café da manhã reforçado, é parada obrigatória.",
            '../img/panqueca.jpg', 'published', 12, 121,
        ],
        'restaurante' => [
            'rafael', 'Jantar especial com vista para a cidade',
            'O Mirante Bistrô combina cozinha contemporânea, bom serviço e uma vista linda do terraço.',
            "Escolhi o Mirante Bistrô para comemorar um aniversário de namoro e acertei em cheio.\n\nO restaurante fica no último andar de um prédio no centro, e as mesas do terraço têm uma vista incrível da cidade ao entardecer. O menu degustação tem cinco etapas; os destaques foram o risoto de cogumelos frescos e o filé ao molho de vinho tinto com purê de mandioquinha.\n\nO serviço é atencioso sem ser invasivo, e o sommelier ajudou a escolher uma harmonização dentro do nosso orçamento.\n\nNão é um lugar barato, mas para uma ocasião especial vale cada centavo. Reserve com antecedência e peça uma mesa no terraço.",
            '../img/restaurante.jpg', 'published', 8, 143,
        ],
        'hamburguer' => [
            'rafael', 'Hambúrguer artesanal sem frescura',
            'O Brasa Burger acerta no ponto da carne e no pão de fermentação natural.',
            "O Brasa Burger é aquele lugar simples, de balcão, que você descobre por indicação de amigo.\n\nO blend da casa tem 180 gramas, é feito com fraldinha e acém e chega ao ponto pedido — o meu veio malpassado, suculento e com uma crosta bem marcada da chapa. O pão de fermentação natural é levemente adocicado e segura bem o molho.\n\nAs batatas rústicas vêm com casca e alecrim, e a maionese de alho da casa é viciante. O milkshake de doce de leite também merece menção.\n\nPreço honesto e atendimento rápido. Ótima opção para um almoço sem cerimônia.",
            '../img/hamburguer.png', 'published', 5, 67,
        ],
        'feijoada' => [
            'mariana', 'Feijoada de sábado com samba ao vivo',
            'No Boteco da Esquina, a feijoada completa vem acompanhada de roda de samba e caipirinha.',
            "Sábado é dia de feijoada, e o Boteco da Esquina leva essa tradição a sério.\n\nA feijoada completa é servida em panela de barro, com carnes separadas, couve refogada na hora, farofa crocante, laranja e torresmo. O feijão é bem temperado e encorpado, sem excesso de gordura.\n\nA partir das 14h começa uma roda de samba que deixa o clima ainda mais animado. A caipirinha de limão com cachaça artesanal é ótima para acompanhar.\n\nChegue cedo, porque as mesas acabam rápido e a feijoada costuma esgotar antes das 16h.",
            '../img/feijoada.png', 'published', 3, 41,
        ],
        'rascunho' => [
            'demo', 'Rota de food trucks no parque',
            'Rascunho: lista dos food trucks que visitei no último fim de semana.',
            "Ainda preciso organizar as fotos e completar as avaliações de cada food truck.\n\n- Tacos mexicanos: muito bons\n- Pastel: crocante, recheio generoso\n- Churros: faltou provar",
            null, 'draft', 1, 0,
        ],
    ];

    // [artigo, usuário que avaliou, nota, dias atrás]
    $ratings = [
        ['pizza', 'mariana', 5, 26], ['pizza', 'rafael', 5, 25], ['pizza', 'juliana', 4, 22], ['pizza', 'pedro', 5, 20],
        ['sushi', 'demo', 5, 23], ['sushi', 'mariana', 4, 21], ['sushi', 'rafael', 5, 19], ['sushi', 'pedro', 4, 18],
        ['cafe', 'demo', 4, 18], ['cafe', 'juliana', 5, 17], ['cafe', 'rafael', 4, 15],
        ['lasanha', 'demo', 4, 14], ['lasanha', 'mariana', 3, 13], ['lasanha', 'juliana', 4, 11],
        ['panqueca', 'mariana', 5, 10], ['panqueca', 'juliana', 4, 7], ['panqueca', 'pedro', 4, 4],
        ['restaurante', 'demo', 5, 6], ['restaurante', 'juliana', 5, 5], ['restaurante', 'pedro', 4, 2],
        ['hamburguer', 'demo', 4, 3], ['hamburguer', 'pedro', 3, 2],
        ['feijoada', 'rafael', 5, 1],
    ];

    $database->beginTransaction();
    try {
        $insertUser = $database->prepare(
            "INSERT INTO users (name, email, password_hash, bio, created_at)
             VALUES (:name, :email, :password_hash, :bio, datetime('now', :offset))"
        );
        $passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
        $userIds = [];
        foreach ($users as $key => [$name, $email, $bio, $daysAgo]) {
            $existing = $database->prepare('SELECT id FROM users WHERE email = :email');
            $existing->execute([':email' => $email]);
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                $userIds[$key] = (int) $existingId;
                continue;
            }

            $insertUser->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':bio' => $bio,
                ':offset' => "-$daysAgo days",
            ]);
            $userIds[$key] = (int) $database->lastInsertId();
        }

        $insertArticle = $database->prepare(
            "INSERT INTO articles (author_id, title, summary, content, cover_image, status, created_at, views)
             VALUES (:author_id, :title, :summary, :content, :cover_image, :status, datetime('now', :offset), :views)"
        );
        $articleIds = [];
        foreach ($articles as $key => [$author, $title, $summary, $content, $cover, $status, $daysAgo, $views]) {
            $insertArticle->execute([
                ':author_id' => $userIds[$author],
                ':title' => $title,
                ':summary' => $summary,
                ':content' => $content,
                ':cover_image' => $cover,
                ':status' => $status,
                ':offset' => "-$daysAgo days",
                ':views' => $views,
            ]);
            $articleIds[$key] = (int) $database->lastInsertId();
        }

        $insertRating = $database->prepare(
            "INSERT OR IGNORE INTO article_ratings (article_id, user_id, rating, created_at, updated_at)
             VALUES (:article_id, :user_id, :rating, datetime('now', :offset), datetime('now', :offset))"
        );
        foreach ($ratings as [$article, $user, $rating, $daysAgo]) {
            $insertRating->execute([
                ':article_id' => $articleIds[$article],
                ':user_id' => $userIds[$user],
                ':rating' => $rating,
                ':offset' => "-$daysAgo days",
            ]);
        }

        $database->commit();
    } catch (Throwable $exception) {
        $database->rollBack();
        throw $exception;
    }
}
