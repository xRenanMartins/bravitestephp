<?php

    /*Escreva uma função que receba uma string de colchetes como entrada e determine se a
    ordem dos parênteses é válida. Um colchete é considerado qualquer um dos seguintes
    caracteres: (, ), {, }, [, ou ].
    Dizemos que uma sequência de colchetes é válida se as seguintes condições forem
    atendidas:
    ● Não contém colchetes sem correspondência.
    ● O subconjunto de colchetes dentro dos limites de um par de colchetes correspondente é
    também um par de colchetes.*/

    function isValidBracketSequence($sequence) {
        $stack = [];
        $bracketsOpen = ['(', '{', '['];
        $bracketsClose = [')', '}', ']'];
        $bracketCombination = array_combine($bracketsClose, $bracketsOpen);
    
        for ($x = 0; $x < strlen($sequence); $x++) {    
            if (in_array($sequence[$x], $bracketsOpen)) {
                array_push($stack, $sequence[$x]);
            } elseif (in_array($sequence[$x], $bracketsClose)) {
                if (empty($stack) || array_pop($stack) !== $bracketCombination[$sequence[$x]]) {
                    return false;
                }
            }
        }
        
        // return empty($stack);
        return empty($stack) ? "é válida</br>": "não é válido</br>";
    }
    
    // Exemplos:
    echo isValidBracketSequence("(){}[]");
    echo isValidBracketSequence("[{()}](){}");
    echo isValidBracketSequence("[]{()");
    echo isValidBracketSequence("[{)]");
    echo isValidBracketSequence("[{(}])");