<?php
App::uses('Controller', 'Controller');

class AppController extends Controller {

    public $images_path;
    public $files_path;
    public $dias_semana_str = array('Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado');
    public $dias_semana_abrev = array('dom','seg','ter','qua','qui','sex','sab');
    public $dias_mes_abrev = array('', 'jan','fev','mar','abr','mai','jun','jul','ago', 'set', 'out', 'nov', 'dez');
    public $meses_abrev = array('', 'jan','fev','mar','abr','mai','jun','jul','ago', 'set', 'out', 'nov', 'dez');
    public $quadra_de_padel_subcategoria = 7;
    
    public $list_odd_color = "#FFFFFF";
    public $list_even_color = "#f7f7f7";
    public $phone_ddi = [
        'Brasil' => '55',
        'Uruguai' => '598',
    ];
    public $services_colors = [
        "#42288e",
        "#444359",
        "#525252",
        "#594343",
        "#770f0f",
        "#2a1313",
        "#af1a1a",
        "#161835",
        "#000000",
        "#CCCCCC",
        "#FFFFFF",
    ];
    public $proximas_fases = [
        1 => [
            'fases'=> [
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_grupo' => 1,
                            'time_2_grupo' => 1,
                            'time_1_posicao' => 2,
                            'time_2_posicao' => 3
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 2,
                            'time_1_grupo' => 1,
                            'time_1_posicao' => 1,
                            'time_2_jogo' => 1,
                        ]
                    ]
                ],
            ],
        ],
        2 => [
            'fases'=> [
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_grupo' => 1,
                            'time_2_grupo' => 2,
                            'time_1_posicao' => 1,
                            'time_2_posicao' => 2
                        ],
                        [
                            'id' => 2,
                            'time_1_grupo' => 1,
                            'time_2_grupo' => 2,
                            'time_1_posicao' => 2,
                            'time_2_posicao' => 1
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 3,
                            'time_1_jogo' => 1,
                            'time_2_jogo' => 2
                        ]
                    ]
                ],
            ],
        ],
        3 => [
            'fases'=> [
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 3,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 3,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 3,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_jogo' => 1
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_jogo' => 2
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 5,
                            'time_1_jogo' => 3,
                            'time_2_jogo' => 4
                        ]
                    ]
                ],
            ],
        ],
        4 => [
            'fases'=> [
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 4,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 3,
                        ],
                        [
                            'id' => 3,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 2,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 1,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 5,
                            'time_1_jogo' => 1,
                            'time_2_jogo' => 2
                        ],
                        [
                            'id' => 6,
                            'time_1_jogo' => 3,
                            'time_2_jogo' => 4
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 7,
                            'time_1_jogo' => 5,
                            'time_2_jogo' => 6
                        ]
                    ]
                ],
            ],
        ],
        5 => [
            'fases'=> [
                [
                    'nome' => "Oitavas de Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 4,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 3,
                        ],
                    ]
                ],
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 3,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 5,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 5,
                        ],
                        [
                            'id' => 5,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_jogo' => 1,
                        ],
                        [
                            'id' => 6,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_jogo' => 2,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 7,
                            'time_1_jogo' => 3,
                            'time_2_jogo' => 6
                        ],
                        [
                            'id' => 8,
                            'time_1_jogo' => 4,
                            'time_2_jogo' => 5
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 9,
                            'time_1_jogo' => 7,
                            'time_2_jogo' => 8
                        ]
                    ]
                ],
            ],
        ],
        6 => [
            'fases'=> [
                [
                    'nome' => "Oitavas de Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 6,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 5,
                        ],
                        [
                            'id' => 3,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 5,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 6,
                        ],
                    ]
                ],
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 5,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_jogo' => 4,
                        ],
                        [
                            'id' => 6,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_jogo' => 3,
                        ],
                        [
                            'id' => 7,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_jogo' => 2,
                        ],
                        [
                            'id' => 8,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_jogo' => 1,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 9,
                            'time_1_jogo' => 5,
                            'time_2_jogo' => 7
                        ],
                        [
                            'id' => 10,
                            'time_1_jogo' => 6,
                            'time_2_jogo' => 8
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 11,
                            'time_1_jogo' => 9,
                            'time_2_jogo' => 10
                        ]
                    ]
                ],
            ],
        ],
        7 => [
            'fases'=> [
                [
                    'nome' => "Oitavas de Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 2,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 7,
                        ],
                        [
                            'id' => 3,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 5,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 6,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 6,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 5,
                        ],
                        [
                            'id' => 5,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 7,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 4,
                        ],
                        [
                            'id' => 6,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 3,
                        ],
                    ]
                ],
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 7,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_jogo' => 4,
                        ],
                        [
                            'id' => 8,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_jogo' => 3,
                        ],
                        [
                            'id' => 9,
                            'time_1_jogo' => 1,
                            'time_2_jogo' => 5,
                        ],
                        [
                            'id' => 10,
                            'time_1_jogo' => 2,
                            'time_2_jogo' => 6,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 11,
                            'time_1_jogo' => 7,
                            'time_2_jogo' => 9
                        ],
                        [
                            'id' => 12,
                            'time_1_jogo' => 8,
                            'time_2_jogo' => 10
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 13,
                            'time_1_jogo' => 11,
                            'time_2_jogo' => 12
                        ]
                    ]
                ],
            ],
        ],
        8 => [
            'fases'=> [
                [
                    'nome' => "Oitavas de Final",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 8,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 7,
                        ],
                        [
                            'id' => 3,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 6,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 5,
                        ],
                        [
                            'id' => 5,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 5,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 4,
                        ],
                        [
                            'id' => 6,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 6,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 3,
                        ],
                        [
                            'id' => 7,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 7,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 2,
                        ],
                        [
                            'id' => 8,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 8,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 1,
                        ],
                    ]
                ],
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 9,
                            'time_1_jogo' => 1,
                            'time_2_jogo' => 4,
                        ],
                        [
                            'id' => 10,
                            'time_1_jogo' => 2,
                            'time_2_jogo' => 3,
                        ],
                        [
                            'id' => 11,
                            'time_1_jogo' => 5,
                            'time_2_jogo' => 8,
                        ],
                        [
                            'id' => 12,
                            'time_1_jogo' => 6,
                            'time_2_jogo' => 7,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 13,
                            'time_1_jogo' => 11,
                            'time_2_jogo' => 12
                        ],
                        [
                            'id' => 14,
                            'time_1_jogo' => 9,
                            'time_2_jogo' => 10
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 15,
                            'time_1_jogo' => 13,
                            'time_2_jogo' => 14
                        ]
                    ]
                ],
            ],
        ],
        9 => [
            'fases'=> [
                [
                    'nome' => "Décima Sextas",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 9,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 6,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 7,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 8,
                        ],
                    ]
                ],
                [
                    'nome' => "Oitavas de Final",
                    'jogos' => [
                        [
                            'id' => 3,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_jogo' => 1,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_jogo' => 2,
                        ],
                        [
                            'id' => 5,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 8,
                        ],
                        [
                            'id' => 6,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 1,
                        ],
                        [
                            'id' => 7,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 5,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 2,
                        ],
                        [
                            'id' => 8,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 6,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 3,
                        ],
                        [
                            'id' => 9,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 7,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 4,
                        ],
                        [
                            'id' => 10,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 9,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 5,
                        ],
                    ]
                ],
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 11,
                            'time_1_jogo' => 3,
                            'time_2_jogo' => 7,
                        ],
                        [
                            'id' => 12,
                            'time_1_jogo' => 4,
                            'time_2_jogo' => 8,
                        ],
                        [
                            'id' => 13,
                            'time_1_jogo' => 5,
                            'time_2_jogo' => 9,
                        ],
                        [
                            'id' => 14,
                            'time_1_jogo' => 6,
                            'time_2_jogo' => 10,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 15,
                            'time_1_jogo' => 11,
                            'time_2_jogo' => 13
                        ],
                        [
                            'id' => 16,
                            'time_1_jogo' => 12,
                            'time_2_jogo' => 14
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 17,
                            'time_1_jogo' => 15,
                            'time_2_jogo' => 16
                        ]
                    ]
                ],
            ],
        ],
        10 => [
            'fases'=> [
                [
                    'nome' => "Décima Sextas",
                    'jogos' => [
                        [
                            'id' => 1,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 8,
                        ],
                        [
                            'id' => 2,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 7,
                        ],
                        [
                            'id' => 3,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 6,
                        ],
                        [
                            'id' => 4,
                            'time_1_posicao' => 2,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 5,
                        ],
                    ]
                ],
                [
                    'nome' => "Oitavas de Final",
                    'jogos' => [
                        [
                            'id' => 5,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 1,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 10,
                        ],
                        [
                            'id' => 6,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 2,
                            'time_2_posicao' => 1,
                            'time_2_grupo' => 9,
                        ],
                        [
                            'id' => 7,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 3,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 9,
                        ],
                        [
                            'id' => 8,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 4,
                            'time_2_posicao' => 2,
                            'time_2_grupo' => 10,
                        ],
                        [
                            'id' => 9,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 5,
                            'time_2_jogo' => 1
                        ],
                        [
                            'id' => 10,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 6,
                            'time_2_jogo' => 2,
                        ],
                        [
                            'id' => 11,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 7,
                            'time_2_jogo' => 3,
                        ],
                        [
                            'id' => 12,
                            'time_1_posicao' => 1,
                            'time_1_grupo' => 8,
                            'time_2_jogo' => 4,
                        ],
                    ]
                ],
                [
                    'nome' => "Quartas de Final",
                    'jogos' => [
                        [
                            'id' => 13,
                            'time_1_jogo' => 5,
                            'time_2_jogo' => 12,
                        ],
                        [
                            'id' => 14,
                            'time_1_jogo' => 6,
                            'time_2_jogo' => 11,
                        ],
                        [
                            'id' => 15,
                            'time_1_jogo' => 7,
                            'time_2_jogo' => 10,
                        ],
                        [
                            'id' => 16,
                            'time_1_jogo' => 8,
                            'time_2_jogo' => 9,
                        ]
                    ]
                ],
                [
                    'nome' => "Semi Final",
                    'jogos' => [
                        [
                            'id' => 17,
                            'time_1_jogo' => 13,
                            'time_2_jogo' => 15
                        ],
                        [
                            'id' => 18,
                            'time_1_jogo' => 14,
                            'time_2_jogo' => 16
                        ]
                    ]
                ],
                [
                    'nome' => "Final",
                    'jogos' => [
                        [
                            'id' => 19,
                            'time_1_jogo' => 17,
                            'time_2_jogo' => 18
                        ]
                    ]
                ],
            ],
        ]
    ];

    public function __construct($request = null, $response = null) {
        parent::__construct($request, $response);
        $this->images_path = getenv('IMAGES_PATH');
        $this->files_path = getenv('FILES_PATH');
    }

    
    public function beforeFilter() {
        parent::beforeFilter();
            App::import("Vendor", "FacebookAuto", array("file" => "facebook/src/Facebook/autoload.php"));
            // $this->response->header('Access-Control-Allow-Origin','*');
            // $this->response->header('Access-Control-Allow-Methods','*');
            // $this->response->header('Access-Control-Allow-Headers','X-Requested-With');
            // $this->response->header('Access-Control-Allow-Headers','Content-Type, x-xsrf-token');
            // $this->response->header('Access-Control-Max-Age','172800');
    }

    public function floatEnBr($val){
        return number_format($val, 2, ',', '.');
    }

	public function datetimeBrEn( $data ){

        if (strpos($data, "/") === false) {
            return $data;
        }

		list( $data, $hora ) = explode(' ', $data);
		$data = explode("/",$data);
		$data = $data[2]."-".$data[1]."-".$data[0];
		$data = date("Y-m-d", strtotime($data));
		return $data." ".$hora;
	}

	public function dateBrEn( $data ){

        if (strpos($data, "/") === false) {
            return $data;
        }

		$data = explode("/",$data);
		$data = $data[2]."-".$data[1]."-".$data[0];
		$data = date("Y-m-d", strtotime($data));
		return $data;
	}

	public function dateEnBr( $data ){
		return date("d/m/Y", strtotime($data));
	}
    
    function timeToMinutes($time){
        $time = explode(':', $time);
        return ($time[0]*60) + ($time[1]) + ($time[2]/60);
    }

    public function verificaValidadeToken($usuario_token, $usuario_email = null){
        $this->loadModel('Token');

        if ( $usuario_email == null ) {
            $dados_token = $this->Token->find('first',array(
                'fields' => array(
                    'Token.id',
                    'Token.token',
                    'Token.data_validade',
                    'Token.usuario_id',
                ),
                'conditions' => array(
                    'Token.token' => $usuario_token,
                    'Token.data_validade >=' => date("Y-m-d"),
                    'Token.usuario_id IS NULL',
                ),
            ));

        } else {
            $dados_token = $this->Token->find('first',array(
                'fields' => array(
                    'Usuario.id',
                    'Usuario.nome',
                    'Usuario.telefone',
                    'Usuario.email',
                    'Usuario.img',
                    'Usuario.nivel_id',
                    'Usuario.cliente_id',
                    'Token.id',
                    'Token.token',
                    'Token.data_validade',
                ),
                'conditions' => array(
                    'Usuario.email' => $usuario_email,
                    'Token.token' => $usuario_token,
                    'Token.data_validade >=' => date("Y-m-d"),
                    'Usuario.ativo' => 'Y'
                ),
                'link' => array(
                    'Usuario'
                )
            ));

        }

        if (count($dados_token) > 0){
            return $dados_token;
        }
        return false;
    }

	public function sendNotification( 
        $arr_ids = array(),
        $agendamento_id = null, 
        $titulo = "", 
        $mensagem = "", 
        $motivo = "agendamento", 
        $group = 'geral', 
        $group_message = '',
        $promocao_id = null
    ){
        
        $send_notifications = getenv('SEND_NOTIFICATIONS');

        if ( $send_notifications === "FALSE" ) {
            return true;
        }

		if ( count($arr_ids) == 0 )
			return false;
	
		if ( $agendamento_id == null )
			return false;

		if ( $mensagem == "" ) {
			$mensagem = $titulo;
        }

		$heading = array(
			"en" => $titulo
		);

		$content = array(
			"en" => $mensagem
        );

        if ( $group_message == '' ) {
            $group_message = (object)["en"=> '$[notif_count] Notificações'];
        }

        $group_message = (object)$group_message;

        $arr_ids_app = array();
		foreach( $arr_ids as $id ) {
			$arr_ids_app[] = $id;
		}

        $fields = array(
            'app_id' => "b3d28f66-5361-4036-96e7-209aea142529",
            'include_player_ids' => $arr_ids_app,
            'data' => [
                "agendamento_id" => $agendamento_id, 
                'motivo' => $motivo,
                'promocao_id' => $promocao_id
            ],
            //'small_icon' => 'https://www.zapshop.com.br/ctff/restfull/pushservice/icons/logo_icon.png',
            //'large_icon' => 'https://www.zapshop.com.br/ctff/restfull/pushservice/icons/logo_icon_large.png',
            'android_group' =>  $group,
            'android_group_message' => $group_message,
            'headings' => $heading,
            'contents' => $content,
        );
        
        $fields = json_encode($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Authorization: Basic ZWM2M2YyMjQtOTQ4My00MjI2LTg0N2EtYThiZmRiNzM5N2Nk'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        if ( isset($response['id']) && !empty($response['id']) ) {
            $this->loadModel('Notificacao');
            $dados_salvar = [
                0 => [
                    'id_one_signal' => $response['id'],
                    'message' => $mensagem,
                    'title' => $titulo,
                    'agendamento_id' => $agendamento_id,
                    'promocao_id' => $promocao_id,
                    'json' => json_encode($response),
                ]
      
            ];

            if ( count($arr_ids_app) > 0 ) {
                foreach( $arr_ids_app as $key_player_id => $player_id ){
                    $dados_salvar[0]['NotificacaoUsuario'][] = ['token' => $player_id];
                }
            }
     
            $this->Notificacao->create();
            return $this->Notificacao->saveAll($dados_salvar, ['deep' => true]) !== false;

        } else {
            $this->log($response, 'debug');
        }

        return true;


    }

	public function sendNotificationNew ( 
        $usuario_id = null,
        $arr_ids = [],
        $registro_id = null, 
        $agendamento_data = null,
        $motivo = null, 
        $group_message = ''
    ){
        
        $send_notifications = getenv('SEND_NOTIFICATIONS');

        if ( $send_notifications === "FALSE" ) {
            return true;
        }

		if ( count($arr_ids) == 0 )
			return false;

        if ( empty($motivo) ) {
            return false;
        }

        // Se foi um agendamento feito pela empresa mas não tem id do usuário
        if ( empty($usuario_id) && $motivo === 'agendamento_empresa' ) {
            return false;
        }

        $this->loadModel('NotificacaoMotivo');
        $motivo_dados = $this->NotificacaoMotivo->find('first',[
            'conditions' => [
                'nome' => $motivo
            ],
            'link' => []
        ]);

        if ( count($motivo_dados) === 0 ) {
            return false;
        }

        if ( !empty($motivo_dados['NotificacaoMotivo']['permissao']) ) {  
     
            $this->loadModel('NotificacaoConfiguracaoUsuario');
            $checkPermission = $this->NotificacaoConfiguracaoUsuario->checkPermission($usuario_id, $motivo_dados['NotificacaoMotivo']['permissao']);

            if ( !$checkPermission ) {
                return true;
            }
        }

        $titulo = $motivo_dados['NotificacaoMotivo']['titulo_notificacao'];
        $mensagem = $motivo_dados['NotificacaoMotivo']['msg_notificacao'];
        $group = $motivo_dados['NotificacaoMotivo']['grupo'];
        $notification_data = [];
        $agendamento_data_hora = null;

        if ( !empty($registro_id) && in_array($motivo, ['agendamento_empresa', 'agendamento_usuario', 'cancelamento_empresa', 'cancelamento_usuario', 'lembrete_agendamento', 'lembrete_agendamento_torneio']) ) {
    
            $this->loadModel('Agendamento');

            $dados_agendamento = $this->Agendamento->find('first',[
                'fields' => [
                    'DATE(Agendamento.horario) AS data',
                    'TIME(Agendamento.horario) AS hora',
                    'Cliente.nome',
                    'ClienteServico.nome',
                    'Usuario.nome',
                    'Torneio.nome'
                ],
                'conditions' => [
                    'Agendamento.id' => $registro_id
                ],
                'link' => [
                    'Cliente',
                    'Torneio',
                    'ClienteServico',
                    'ClienteCliente' => [
                        'Usuario'
                    ]
                ]
            ]);

            if ( count($dados_agendamento) == 0 ) {
                return false;
            }

            $placeholders = [
                "{{empresa_nome}}", 
                "{{hora_agendamento}}", 
                "{{dia_agendamento}}", 
                "{{servico_nome}}", 
                "{{usuario_nome}}", 
                "{{torneio_nome}}"
            ];

            $values = [
                $dados_agendamento['Cliente']['nome'],
                substr($dados_agendamento[0]['hora'], 0, 5),
                date('d/m',strtotime($agendamento_data)),
                $dados_agendamento['ClienteServico']['nome'],
                $dados_agendamento['Usuario']['nome'],
                $dados_agendamento['Torneio']['nome']
            ];

            $mensagem = trim(str_replace($placeholders, $values, $mensagem));

            $notification_data = [
                "registro_id" => $registro_id, 
                "agendamento_horario" => date('Y-m-d', strtotime($agendamento_data)).' '.$dados_agendamento[0]['hora'], 
                'motivo' => $motivo,
            ];

            $agendamento_data_hora = $agendamento_data.' '.$dados_agendamento[0]['hora'];
        }
        else if (in_array($motivo, ['resposta_positiva_convite_jogo', 'resposta_negativa_convite_jogo', 'confirmacao_participacao_convite', 'cancelamento_participacao_convite', 'cancelamento_angedamento_convite'])) {
            $this->loadModel('AgendamentoConvite');
            $this->loadModel('ClienteCliente');
            $dados_convite = $this->AgendamentoConvite->find('first', [
                'fields' => [
                    'Agendamento.id',
                    'AgendamentoConvite.horario',
                    'AgendamentoConvite.cliente_cliente_id',
                    'Usuario.nome'
                ],
                'conditions' => [
                    'AgendamentoConvite.id' => $registro_id
                ],
                'link' => [
                    'Agendamento' => ['ClienteCliente' => ['Usuario']]
                ]
            ]);

            if ( count($dados_convite) == 0 ) {
                return false;
            }

            $dados_usuario_convidado = $this->ClienteCliente->finUserData($dados_convite['AgendamentoConvite']['cliente_cliente_id'], ['Usuario.id', 'Usuario.nome']);

            $placeholders = [
                "{{convidado_mome}}", 
                "{{dono_agendamento_nome}}"
            ];

            $values = [
                $dados_usuario_convidado['nome'], 
                $dados_convite['Usuario']['nome']
            ];

            $mensagem = trim(str_replace($placeholders, $values, $mensagem));

            $notification_data = [
                "registro_id" => $registro_id, 
                "agendamento_horario" => $dados_convite['AgendamentoConvite']['horario'], 
                "agendamento_id" => $dados_convite['Agendamento']['id'], 
                'motivo' => $motivo,
            ];
        }
        else if ( $motivo === 'convite_jogo' ) {
            $notification_data = [
                "registro_id" => $registro_id, 
                'motivo' => $motivo,
            ];
        } 
        else if ( $motivo === 'nova_promocao' ) {

            $this->loadModel('Promocao');
            $promocao = $this->Promocao->find('first', [
                'fields' => [
                    'Cliente.nome'
                ],
                'conditions' => [
                    'Promocao.id' => $registro_id
                ],
                'link' => [
                    'Cliente'
                ]
            ]);

            $placeholders = [
                "{{empresa_nome}}", 
            ];

            $values = [
                $promocao['Cliente']['nome'], 
            ];

            $mensagem = trim(str_replace($placeholders, $values, $mensagem));

            $notification_data = [
                "registro_id" => $registro_id, 
                'motivo' => $motivo,
            ];

        }
        else if ( $motivo === 'novo_torneio_padel' || $motivo === 'jogos_torneio_padel_liberados' ) {

            $this->loadModel('Torneio');
            $torneio = $this->Torneio->find('first', [
                'fields' => [
                    'Cliente.nome',
                    'Torneio.inicio',
                    'Torneio.fim',
                    'Torneio.nome',
                    'Torneio.inscricoes_de',
                    'Torneio.inscricoes_ate'
                ],
                'conditions' => [
                    'Torneio.id' => $registro_id
                ],
                'link' => [
                    'Cliente'
                ]
            ]);

            $placeholders = [
                "{{empresa_nome}}", 
                "{{torneio_nome}}",
                "{{torneio_data_inicio}}",
                "{{torneio_data_fim}}",
                "{{torneio_inscricoes_inicio}}",
                "{{torneio_inscricoes_fim}}",
            ];

            $values = [
                $torneio['Cliente']['nome'], 
                $torneio['Torneio']['nome'], 
                date('d/m', strtotime($torneio['Torneio']['inicio'])),
                date('d/m', strtotime($torneio['Torneio']['fim'])),
                date('d/m', strtotime($torneio['Torneio']['inscricoes_de'])),
                date('d/m', strtotime($torneio['Torneio']['inscricoes_ate'])),
            ];

            $mensagem = trim(str_replace($placeholders, $values, $mensagem));

            $notification_data = [
                "registro_id" => $registro_id, 
                'motivo' => $motivo,
            ];

        }
        else if ( $motivo === 'lembrete_avaliar' ) {

            $this->loadModel('ClienteServico');
            $servico = $this->ClienteServico->find('first', [
                'fields' => [
                    'ClienteServico.nome',
                    'Cliente.nome'
                ],
                'conditions' => [
                    'ClienteServico.id' => $registro_id
                ],
                'link' => [
                    'Cliente'
                ]
            ]);

            $placeholders = [
                "{{servico_nome}}",
                "{{empresa_nome}}",
            ];

            $values = [
                $servico['ClienteServico']['nome'],
                $servico['Cliente']['nome']
            ];

            $mensagem = trim(str_replace($placeholders, $values, $mensagem));

            $notification_data = [
                "registro_id" => $registro_id, 
                'motivo' => $motivo,
            ];

        }

		$heading = array(
			"en" => $titulo
		);

		$content = array(
			"en" => $mensagem
        );

        if ( $group_message == '' ) {
            $group_message = (object)["en"=> '$[notif_count] Notificações'];
        }

        $group_message = (object)$group_message;

        $arr_ids_app = array();
		foreach( $arr_ids as $id ) {
			$arr_ids_app[] = $id;
		}

        $fields = [
            'app_id' => 'b3d28f66-5361-4036-96e7-209aea142529',
            'include_player_ids' => $arr_ids_app,
            'data' => $notification_data,
            'small_icon' => 'ic_stat_onesignal_default',
            'large_icon' => 'ic_onesignal_large_icon_default',
            'android_group' =>  $group,
            'android_group_message' => $group_message,
            'headings' => $heading,
            'contents' => $content,
        ];
        
        $fields = json_encode($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Authorization: Basic ZWM2M2YyMjQtOTQ4My00MjI2LTg0N2EtYThiZmRiNzM5N2Nk'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        if ( isset($response['id']) && !empty($response['id']) ) {
            $this->loadModel('Notificacao');
            $dados_salvar = [
                0 => [
                    'id_one_signal' => $response['id'],
                    'message' => $mensagem,
                    'title' => $titulo,
                    'registro_id' => $registro_id,
                    'notificacao_motivo_id' => $motivo_dados['NotificacaoMotivo']['id'],
                    'agendamento_data_hora' => $agendamento_data_hora,
                    'json' => json_encode($response),
                ]
      
            ];

            if ( count($arr_ids_app) > 0 ) {
                foreach( $arr_ids_app as $key_player_id => $player_id ){
                    $dados_salvar[0]['NotificacaoUsuario'][] = ['token' => $player_id, 'usuario_id' => $usuario_id];
                }
            }
     
            $this->Notificacao->create();
            return $this->Notificacao->saveAll($dados_salvar, ['deep' => true]) !== false;

        } else {
            $this->log($response, 'debug');
            $this->log($one_signal_app_id, 'debug');
        }

        return true;


    }

    public function saveInvitesAndSendNotification($clientes_clientes_ids, $dados_agendamento) {

        $this->loadModel('AgendamentoConvite');
        $this->loadModel('Token');
        $this->loadModel('ClienteCliente');

        $dados_salvar = [];
        foreach($clientes_clientes_ids as $key => $cli_cli_id) {

            $v_convite = $this->AgendamentoConvite->find('first',[
                'conditions' => [
                    'AgendamentoConvite.cliente_cliente_id' => $cli_cli_id,
                    'AgendamentoConvite.agendamento_id' => $dados_agendamento['id'],
                    'AgendamentoConvite.horario' => $dados_agendamento['horario'],
                ],
                'link' => []
            ]);

            if ( count($v_convite) > 0 ) {
                continue;
            }

            $dados_salvar = [
                'agendamento_id' => $dados_agendamento['id'],
                'cliente_cliente_id' => $cli_cli_id,
                'horario' => $dados_agendamento['horario'],
            ];
            
            $this->AgendamentoConvite->create();
            $convite_salvo = $this->AgendamentoConvite->save($dados_salvar);

 
            if ( $convite_salvo ) {

                $dados_usuario = $this->ClienteCliente->finUserData($cli_cli_id, ['Usuario.id']);

                if ( count($dados_usuario) == 0 || empty($dados_usuario['id']) ) {
                    continue;
                }

                $usuario_id = $dados_usuario['id'];
                $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);
            
                if( count($notifications_ids) > 0 ) {

                    $this->sendNotificationNew(
                        $usuario_id,
                        $notifications_ids, 
                        $convite_salvo['AgendamentoConvite']['id'],
                        null,
                        'convite_jogo',
                        ["en"=> '$[notif_count] Novos Convites Para Jogos']
                    );
                }

            }
        }

        if ( count($dados_salvar) == 0 )
            return true; 
        
        return true;
        
    }

    public function enviaNotificacaoDeCancelamento($cancelado_por, $horario, $dados_agendamento) {

        //busca os ids do onesignal do usuário a ser notificado do cancelamento do horário
        $this->loadModel('Token');
        $this->loadModel('Usuario');

        if ( $cancelado_por === 'cliente' ) {    
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados_agendamento['Usuario']['id']);
            $usuario_id = $dados_agendamento['Usuario']['id'];
            $motivo = "cancelamento_empresa";

            if ( count($notifications_ids) > 0 ) {
                $this->sendNotificationNew(
                    $usuario_id,
                    $notifications_ids, 
                    $dados_agendamento['Agendamento']['id'],
                    strpos($horario, ":") === false ? $horario : date('Y-m-d', strtotime($horario)),
                    $motivo,
                    ["en"=> '$[notif_count] Agendamentos Cancelados'],
                );
            }
        } else {
            $motivo = "cancelamento_usuario";
            $usuarios = $this->Usuario->getUsersByClientId($dados_agendamento['Cliente']['id']);

            foreach ( $usuarios as $key => $usuario ) {
                $usuario_id = $usuario['Usuario']['id'];
                $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);
    
                $this->sendNotificationNew(
                    $usuario_id,
                    $notifications_ids, 
                    $dados_agendamento['Agendamento']['id'],
                    strpos($horario, ":") === false ? $horario : date('Y-m-d', strtotime($horario)),
                    $motivo,
                    ["en"=> '$[notif_count] Agendamentos Cancelados'],
                );

            }
        }

    }

    public function avisaConvidadosCancelamento($horario, $agendamento_id) {

        //busca os convites do agendamento que não foram recusados
        $this->loadModel('AgendamentoConvite');
        $this->loadModel('Token');
        $convites = $this->AgendamentoConvite->getNotRecusedUsers($agendamento_id, $this->images_path.'/usuarios/', $horario);

        //se há convites, avisa os candidatos que o agendamento foi cancelado
        if ( count($convites) > 0 ) {
            foreach($convites as $key => $convite) {

                $dados_salvar = [
                    'id' => $convite['AgendamentoConvite']['id'],
                    'horario_cancelado' => 'Y',
                ];
    
                $salvo = $this->AgendamentoConvite->save($dados_salvar);
                if ( $salvo ) {
                    $notifications_ids = $this->Token->getIdsNotificationsUsuario($convite['Usuario']['id']);

                    $this->sendNotificationNew(
                        $convite['Usuario']['id'],
                        $notifications_ids, 
                        $convite['AgendamentoConvite']['id'],
                        null,
                        'cancelamento_angedamento_convite',
                        ["en"=> '$[notif_count] Convites cancelados']
                    );
                }
            }
        }

        return true;
    }

    public function quadra_horarios($servico_id, $data) {

        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('ClienteServicoHorario');
        $this->loadModel('TorneioQuadraPeriodo');
        $this->loadModel('Agendamento');
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoProfissional');

        $dados_servico = $this->ClienteServico->find('first',[
            'conditions' => [
                'ClienteServico.id' => $servico_id
            ],
            'contain' => [
                'ClienteServicoProfissional'
            ]
        ]);


        $horarios = $this->ClienteServicoHorario->listaHorarios($servico_id, $data);

        if ( count($horarios) > 0 ) {

            foreach( $horarios as $key => $horario ) {

                $agendamentos_padrao = $this->Agendamento->agendamentosHorario($servico_id, $data, $horario['time']);
                $agendamentos_fixos = $this->Agendamento->agendamentosHorarioFixo($servico_id, $data, $horario['time']);
                $agendamentos_fixos_futuros = $this->Agendamento->agendamentosHorarioFixoFuturo($servico_id, $data, $horario['time']);

                if ( count($agendamentos_fixos) > 0 ) {
                    $horarios[$key]['enable_fixed_scheduling'] = false;
             
                    $n_fixos_cancelados = $this->AgendamentoFixoCancelado->find('count',[
                        'conditions' => [
                            'AgendamentoFixoCancelado.horario' => $data . ' ' . $horario['time'],
                            'Agendamento.servico_id' => $servico_id
                        ],
                        'link' => [
                            'Agendamento'
                        ]
                    ]);
    
                    $agendamentos_fixos = array_slice($agendamentos_fixos, $n_fixos_cancelados);                
                    
                }

                if ( count($agendamentos_fixos_futuros) > 0 ) {
                    $horarios[$key]['enable_fixed_scheduling'] = false;
                }

                $motivo_indisponivel = null;

                if ( $data." ".$horarios[$key]['time'] < date('Y-m-d H:i:s') ) {
                    $motivo_indisponivel = "Horário já passou";
                }

                if ( $dados_servico['ClienteServico']['tipo'] === 'Quadra' ) {

                    $vagas_por_horario = 1;
                    $reservas_torneio = $this->TorneioQuadraPeriodo->verificaReservaTorneio($servico_id, $data, $horario['time']);

                    if ( count($reservas_torneio) > 0 ) {
                        $motivo_indisponivel = "Haverá torneio nessa quadra nesse dia e hora.";
                    }
    
                    if ( ($vagas_por_horario - count($agendamentos_padrao) - count($agendamentos_fixos)) < 0 ) {
                        $motivo_indisponivel = "Horário ocupado por outro usuário";
                    }
    
                    $horarios[$key]['active'] = count($reservas_torneio) == 0 && ($vagas_por_horario - count($agendamentos_padrao) - count($agendamentos_fixos)) > 0 && $data." ".$horarios[$key]['time'] > date('Y-m-d H:i:s');
                    $horarios[$key]['motivo'] = $motivo_indisponivel;
            
                } else {         

                    if ( $motivo_indisponivel !== null ) {
                        $horarios[$key]['active'] = false;
                        $horarios[$key]['motivo'] = $motivo_indisponivel;
                        $horarios[$key]['prof_disponiveis'] = [];
                        continue;
                    }           

                    $profissionais_disponiveis = [];

                    foreach( $dados_servico['ClienteServicoProfissional'] as $key_profissional => $profissional ) {

                        $verifica_disponibilidade = $this->ClienteServicoProfissional->verifica_disponibilidade($profissional['usuario_id'], $data." ".$horarios[$key]['time']);

                        if ( $verifica_disponibilidade ) {
                            $profissionais_disponiveis[] = $profissional['usuario_id'];
                        }

                    }

                    $horarios[$key]['active'] = count($profissionais_disponiveis) > 0;
                    $horarios[$key]['motivo'] = count($profissionais_disponiveis) === 0 ? "Profissionais indisponíves no horário." : null;
                    $horarios[$key]['prof_disponiveis'] = array_unique($profissionais_disponiveis);

                }


            }

        }

        return $horarios;
    }
    
    public function dateHourEnBr( $data , $r_data, $r_hora ){
		if ($r_data && $r_hora) {
			$data = date("d/m/Y H:s:i", strtotime($data));
		} else if ($r_data) {
			$data = date("d/m/Y", strtotime($data));
		} else if ($r_hora) {
			$data = date("H:s:i", strtotime($data));
		}
		return $data;
    }
    
    public function currencyToFloat($currency) {
		if (!is_float($currency) && preg_match('/\D/', $currency)) {
			return (float) preg_replace('/\D/', '', $currency) / 100;
		}
		return $currency;
    }
    
    public function formatCelular($celular){
        $part1 = substr($celular, 0 ,5);
        $part2 = substr($celular, 5 ,5);

        return ' '.$part1 ."-".$part2;
    }

    public function calculaDatas($tipo, $data_hora_inicio, $data_hora_fim){
        if ( $tipo == 'd' ) {
            // Create two new DateTime-objects...
            $date1 = new DateTime($data_hora_inicio);
            $date2 = new DateTime($data_hora_fim);

            // The diff-methods returns a new DateInterval-object...
            $diff = $date2->diff($date1);

            // Call the format method on the DateInterval-object
            return $diff->format('%d');
        } else if($tipo == 'h') {
            // Create two new DateTime-objects...
            $date1 = new DateTime($data_hora_inicio);
            $date2 = new DateTime($data_hora_fim);

            // The diff-methods returns a new DateInterval-object...
            $diff = $date2->diff($date1);

            // Call the format method on the DateInterval-object
            return $diff->format('%h:%i:%s');

        }

    }

	function validar_cnpj($cnpj)
	{
		$cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
		
		// Valida tamanho
		if (strlen($cnpj) != 14)
			return false;

		// Verifica se todos os digitos são iguais
		if (preg_match('/(\d)\1{13}/', $cnpj))
			return false;	

		// Valida primeiro dígito verificador
		for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++)
		{
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto))
			return false;

		// Valida segundo dígito verificador
		for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++)
		{
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
	}

	function validar_cpf($cpf) {
	
		// Extrai somente os números
		$cpf = preg_replace( '/[^0-9]/is', '', $cpf );
		
		// Verifica se foi informado todos os digitos corretamente
		if (strlen($cpf) != 11) {
			return false;
		}

		// Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
		if (preg_match('/(\d)\1{10}/', $cpf)) {
			return false;
		}

		// Faz o calculo para validar o CPF
		for ($t = 9; $t < 11; $t++) {
			for ($d = 0, $c = 0; $c < $t; $c++) {
				$d += $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ($cpf[$c] != $d) {
				return false;
			}
		}
		return true;

	}

    public function getPayments( $signature_id = null) {
        if ( $signature_id == null ) {
            return false;
        }

        $asaas_url = getenv('ASAAS_API_URL');
        $asaas_token = getenv('ASAAS_API_TOKEN');      

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $asaas_url . '/api/v3/subscriptions/'.$signature_id.'/payments',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_SSL_VERIFYPEER=> 0,
          CURLOPT_SSL_VERIFYHOST=> 0,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'access_token: '.$asaas_token,
            'Cookie: AWSALBTG=+s59gpY4Qyw+lvwBLFGRyT6TbJ4tdpLVLm/DZ+NaupzP98+2MTNvFaxzwCOW6R4RC/dAQp53B94xKiIWr1r1DPW6t8h7Nj3gDBGj9jZJYUn1x2n4WDanw5wAUre01u3H1CUg9Sookotft2BJGPWwg+Cq803/XH+zD7h9YqycJxbH; AWSALBTGCORS=+s59gpY4Qyw+lvwBLFGRyT6TbJ4tdpLVLm/DZ+NaupzP98+2MTNvFaxzwCOW6R4RC/dAQp53B94xKiIWr1r1DPW6t8h7Nj3gDBGj9jZJYUn1x2n4WDanw5wAUre01u3H1CUg9Sookotft2BJGPWwg+Cq803/XH+zD7h9YqycJxbH; JSESSIONID=F93C308F7CEE22776A3360055174DC9884315BB27580A014E3344B83254D4BC152B8F4723F5126259EC7140C7F2DDF2323077F971E2217B3F472052EACBEF379.n1'
          ),
        ));

        $response = curl_exec($curl);

        $errors = curl_error($curl);
        curl_close($curl);

        if ( !empty($errors) ) {
            return false;
        }

        return json_decode($response, true);

    }

}
