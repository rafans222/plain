<?php
set_time_limit(2400);
ini_set('memory_limit', '2048M');

$realizarBusca = false;
$realizarBusca2 = false;
if(isset($_REQUEST['btnBuscar'])){
    $_REQUEST['txtBusca'] = trim($_REQUEST['txtBusca']);
    if($_REQUEST['txtBusca'] != ""){
        $realizarBusca = true;
    }
    $_REQUEST['txtBusca2'] = trim($_REQUEST['txtBusca2']);
    if($_REQUEST['txtBusca2'] != ""){
        $realizarBusca2 = true;
    }
}

if ($realizarBusca){
    require_once "lib/EasyRdf.php";

    EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
    EasyRdf_Namespace::set('dbo',  'http://dbpedia.org/ontology/');
    EasyRdf_Namespace::set('owl',  'http://www.w3.org/2002/07/owl#');
    EasyRdf_Namespace::set('prov', 'http://www.w3.org/ns/prov#');

    $sparqlEndpoint = 'https://lov.linkeddata.es/dataset/lov/sparql';

    $sparql = new EasyRdf_Sparql_Client($sparqlEndpoint);

    //DATA UMA CLASSE PARA PESQUISA, CRIA UMA LISTA DE CLASSES EQUIVALESTES
    $txtQuery = 'SELECT DISTINCT * WHERE {
                        {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    '.$_REQUEST['txtBusca'].' owl:equivalentClass* ?type .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    ?type owl:equivalentClass* '.$_REQUEST['txtBusca'].' .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    '.$_REQUEST['txtBusca'].' rdfs:subClassOf* ?type .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    ?type rdfs:subClassOf* '.$_REQUEST['txtBusca'].' .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    '.$_REQUEST['txtBusca'].' prov:wasDerivedFrom*  ?type .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    ?type prov:wasDerivedFrom* '.$_REQUEST['txtBusca'].' .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        }
                    } ORDER BY ?classLabel LIMIT 1000
                ';

    $result = $sparql->query($txtQuery);

    $vetClassesEquivalentes = array();
    $vetPredicados = array();
    $vetPredicadosEquivalentes = array();

    foreach($result as $key => $value) {
        if ( $value->type != "http://www.w3.org/2002/07/owl#Thing" ) {
            $vetClassesEquivalentes[(string) $value->classLabel] = (string) $value->type;
        }
    }

    //PARA CADA CLASSE EQUIVALENTE SERÃO CONSULTADOS PREDICADOS QUE TENHAM ELA COMO DOMAIN OU RANGE
    foreach($vetClassesEquivalentes as $key => $classe) {

        $txtQuery = 'SELECT DISTINCT * WHERE {
                        {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("D (->)" as ?domainRange) WHERE {

                                ?predicateURI rdfs:domain <'.$classe.'> .
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                        LANG(?predicateLabel) = "en"
                                )
                            }
                        } UNION {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("R (<-)" as ?domainRange) WHERE {

                                ?predicateURI rdfs:range <'.$classe.'> .
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                    LANG(?predicateLabel) = "en"
                                )
                            }
                        }
                    } ORDER BY ?predicate LIMIT 1000';

        $result  = $sparql->query($txtQuery);

        foreach($result as $key2 => $value) {
            $vetPredicados[$classe][(string) $value->predicate]['classeLabel'] = $key;
            $vetPredicados[$classe][(string) $value->predicate]['predicateURI'] = (string) $value->predicateURI;
            $vetPredicados[$classe][(string) $value->predicate]['domainRange'] = (string) $value->domainRange;
        }

    }

    //INÍCIO DA BUSCA POR PREDICADOS RELACIONADOS AOS JÁ ENCONTRADOS
    foreach($vetPredicados as $classe => $vetPropriedades) {
        foreach($vetPropriedades as $key => $val) {

            $txtQuery = 'SELECT DISTINCT * WHERE {
                        {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("sub" as ?subEq) WHERE {
                                <'.$val['predicateURI'].'> rdfs:subPropertyOf* ?predicateURI.
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                       LANG(?predicateLabel) = "en"
                                )
                            }
                        } UNION {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("eq" as ?subEq) WHERE {

                                <'.$val['predicateURI'].'> owl:equivalentProperty* ?predicateURI .
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                    LANG(?predicateLabel) = "en"
                                )
                            }
                        }
                    } ORDER BY ?predicate LIMIT 1000';

            $result  = $sparql->query($txtQuery);


            foreach($result as $key => $value) {
                $vetPredicadosEquivalentes[$val['predicateURI']][(string) $value->predicate]['predicateLabel'] = (string) $value->predicateLabel;
                $vetPredicadosEquivalentes[$val['predicateURI']][(string) $value->predicate]['predicateURI'] = (string) $value->predicateURI;
                $vetPredicadosEquivalentes[$val['predicateURI']][(string) $value->predicate]['subEq'] = (string) $value->subEq;
            }

        }

    }

}//end if($realizarBusca)



if ($realizarBusca2){

    //DATA UMA CLASSE PARA PESQUISA, CRIA UMA LISTA DE CLASSES EQUIVALESTES
    $txtQuery = 'SELECT DISTINCT * WHERE {
                        {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    '.$_REQUEST['txtBusca2'].' owl:equivalentClass* ?type .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    ?type owl:equivalentClass* '.$_REQUEST['txtBusca2'].' .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    '.$_REQUEST['txtBusca2'].' rdfs:subClassOf* ?type .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    ?type rdfs:subClassOf* '.$_REQUEST['txtBusca2'].' .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    '.$_REQUEST['txtBusca2'].' prov:wasDerivedFrom*  ?type .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        } UNION {
                            SELECT (UCASE(STR(?label)) as ?classLabel) ?type WHERE {
                                    ?type prov:wasDerivedFrom* '.$_REQUEST['txtBusca2'].' .
                                    ?type rdfs:label ?label .
                                FILTER (
                                    LANG(?label) = "en"
                                )
                            }
                        }
                    } ORDER BY ?classLabel LIMIT 1000
                ';

    $result = $sparql->query($txtQuery);

    $vetClassesEquivalentes2 = array();
    $vetPredicados2 = array();
    $vetPredicadosEquivalentes2 = array();

    foreach($result as $key => $value) {
        if ( $value->type != "http://www.w3.org/2002/07/owl#Thing" ) {
            $vetClassesEquivalentes2[(string) $value->classLabel] = (string) $value->type;
        }
    }


    //PARA CADA CLASSE EQUIVALENTE SERÃO CONSULTADOS PREDICADOS QUE TENHAM ELA COMO DOMAIN OU RANGE
    foreach($vetClassesEquivalentes2 as $key => $classe) {

        $txtQuery = 'SELECT DISTINCT * WHERE {
                        {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("D (->)" as ?domainRange) WHERE {

                                ?predicateURI rdfs:domain <'.$classe.'> .
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                        LANG(?predicateLabel) = "en"
                                )
                            }
                        } UNION {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("R (<-)" as ?domainRange) WHERE {

                                ?predicateURI rdfs:range <'.$classe.'> .
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                    LANG(?predicateLabel) = "en"
                                )
                            }
                        }
                    } ORDER BY ?predicate LIMIT 1000';

        $result  = $sparql->query($txtQuery);

        foreach($result as $key2 => $value) {
            $vetPredicados2[$classe][(string) $value->predicate]['classeLabel'] = $key;
            $vetPredicados2[$classe][(string) $value->predicate]['predicateURI'] = (string) $value->predicateURI;
            $vetPredicados2[$classe][(string) $value->predicate]['domainRange'] = (string) $value->domainRange;
        }

    }

    //INÍCIO DA BUSCA POR PREDICADOS RELACIONADOS AOS JÁ ENCONTRADOS
    foreach($vetPredicados2 as $classe => $vetPropriedades2) {
        foreach($vetPropriedades2 as $key => $val) {

            $txtQuery = 'SELECT DISTINCT * WHERE {
                        {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("sub" as ?subEq) WHERE {
                                <'.$val['predicateURI'].'> rdfs:subPropertyOf* ?predicateURI.
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                       LANG(?predicateLabel) = "en"
                                )
                            }
                        } UNION {
                            SELECT DISTINCT (UCASE(STR(?predicateLabel)) as ?predicate)
                                            ?predicateLabel
                                            ?predicateURI
                                            ("eq" as ?subEq) WHERE {

                                <'.$val['predicateURI'].'> owl:equivalentProperty* ?predicateURI .
                                ?predicateURI rdfs:label ?predicateLabel.

                                FILTER (
                                    LANG(?predicateLabel) = "en"
                                )
                            }
                        }
                    } ORDER BY ?predicate LIMIT 1000';

            $result  = $sparql->query($txtQuery);

            foreach($result as $key => $value) {
                $vetPredicadosEquivalentes2[$val['predicateURI']][(string) $value->predicate]['predicateLabel'] = (string) $value->predicateLabel;
                $vetPredicadosEquivalentes2[$val['predicateURI']][(string) $value->predicate]['predicateURI'] = (string) $value->predicateURI;
                $vetPredicadosEquivalentes2[$val['predicateURI']][(string) $value->predicate]['subEq'] = (string) $value->subEq;
            }

        }

    }

}//end if($realizarBusca2)

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PLAIN - Predicate Labeling</title>

    <link rel="stylesheet" href="./pure/pure-min.css">
    <link rel="stylesheet" href="./pure/grids-responsive-min.css">
    <link rel="stylesheet" href="./style/blog.css">
    <link rel="stylesheet" href="./style/predlabel.css">
</head>
<body>
<div id="layout" class="pure-g">

    <div class="pure-u-5-24" style="background-color: #666;">
        <div class="header">
            <h1 class="brand-title">PLAIN</h1>
            <h2 class="brand-tagline"><b>P</b>redicate <b>LA</b>bel<b>IN</b>g</h2>
            <form id="frmBusca" name="frmBusca" method="post" class="pure-form pure-form-stacked">
                <fieldset>
                    <label for="txtBusca">Classe 1</label>
                    <input type="text" required id="txtBusca" name="txtBusca" class="pure-input-1" placeholder="Classe 1" value="<?= isset($_REQUEST['txtBusca']) ? $_REQUEST['txtBusca'] : "" ?>">
                    <label for="txtBusca2">Classe 2</label>
                    <input type="text" id="txtBusca2" name="txtBusca2" class="pure-input-1" placeholder="Classe 2" value="<?= isset($_REQUEST['txtBusca2']) ? $_REQUEST['txtBusca2'] : "" ?>">
                    <button type="submit" id="btnBuscar" name="btnBuscar" class="pure-button pure-button-primary">Buscar</button>
                    <br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />
                </fieldset>
            </form>
        </div>
    </div>

    <div class="pure-u-9-24" style="margin: 2px; padding: 5px; border-width: 1px; border-color: #000; border-style: solid;">
        <?php if($realizarBusca){?>
            1<sup>a</sup> Classe pesquisada: <b><?=htmlspecialchars($_REQUEST['txtBusca'], ENT_QUOTES)?></b><br /><br />
            <table class="pure-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Classes Equivalentes*</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($vetClassesEquivalentes) > 0) { ?>
                        <?php $i = 0; ?>
                        <?php foreach($vetClassesEquivalentes as $key => $val) { ?>
                            <?php $classEscuro = ($i++ % 2) ? "class=\"pure-table-odd\"" : "";  ?>
                            <tr <?=$classEscuro?>>
                                <td align="right"><?=$i?></td>
                                <td><?="<a href='".$val."' target='_blank'>".$key?></td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="2">Nenhum resultado retornado</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <p style="font-size: small">*exceto: <?=htmlentities("<http://www.w3.org/2002/07/owl#Thing>")?></p>
        <?php } //end if ?>

        <?php if ($realizarBusca) {?>
            Predicados disponíveis por classe equivalente:<br /><br />
            <table class="pure-table">
                <thead style="vertical-align: middle;">
                    <tr>
                        <th align="center">Classe Domain</th>
                        <th align="center">Predicado</th>
                        <th align="center">Classe Range</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vetPredicados) > 0) { ?>
                        <?php $i = 1; ?>
                        <?php $j = 0; ?>
                        <?php foreach($vetPredicados as $classe => $vetPropriedades) { ?>
                            <?php $apresentaClasse = true; ?>
                            <?php foreach($vetPropriedades as $key => $val) { ?>
                                <?php $classEscuro = ($j++ % 2) ? "" : "";  ?>
                                <tr <?=$classEscuro?> style="border-bottom: 1px solid lightgray;">
                                    <td align="center">
                                        <?php if ($val['domainRange'] == 'D (->)') { echo("<a href='$classe' target='_blank'>".$val['classeLabel']."</a>"); } else { echo(""); } ?>
                                    </td>
                                    <td align="center">
                                        <?="<a href='".$val['predicateURI']."' target='_blank'>".$key."</a>"?>
                                        <?php
                                            foreach($vetPredicadosEquivalentes[$val['predicateURI']] as $keyPeq => $valPeq) {
                                                if ($valPeq['predicateURI'] != $val['predicateURI']) {
                                                    echo "<br /><a href='".$valPeq['predicateURI']."' target='_blank' class='linkMenor'>".$keyPeq."(".$valPeq['subEq'].")</a>";
                                                }
                                            }
                                        ?>
                                    </td>
                                    <td align="center">
                                        <?php if ($val['domainRange'] == 'R (<-)') { echo("<a href='$classe' target='_blank'>".$val['classeLabel']."</a>"); } else { echo(""); } ?>
                                    </td>
                                </tr>
                                <?php $apresentaClasse = false; ?>
                            <?php } ?>
                            <?php $i++; ?>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td align="center" colspan="5">Nenhum resultado retornado</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <p style="font-size: small">
                ** <b>Domain</b> do Predicado: Classe(s) que pode(m) ser Sujeito.<br />
                ** <b>Range</b> do Predicado: Classe(s) que pode(m) ser Objeto.
            </p>
        <?php }//end if($realizarBusca) ?>
    </div>

    <div class="pure-u-9-24" style="margin: 2px; padding: 5px; border-width: 1px; border-color: #000; border-style: solid;">
        <?php if($realizarBusca2){?>
            2<sup>a</sup> Classe pesquisada: <b><?=htmlspecialchars($_REQUEST['txtBusca2'], ENT_QUOTES)?></b><br /><br />
            <table class="pure-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Classes Equivalentes*</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($vetClassesEquivalentes2) > 0) { ?>
                        <?php $i = 0; ?>
                        <?php foreach($vetClassesEquivalentes2 as $key => $val) { ?>
                            <?php $classEscuro = ($i++ % 2) ? "class=\"pure-table-odd\"" : "";  ?>
                            <tr <?=$classEscuro?>>
                                <td align="right"><?=$i?></td>
                                <td><?="<a href='".$val."' target='_blank'>".$key?></td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="2">Nenhum resultado retornado</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <p style="font-size: small">*exceto: <?=htmlentities("<http://www.w3.org/2002/07/owl#Thing>")?></p>
        <?php } //end if($realizarBusca2) ?>

        <?php if ($realizarBusca2) {?>
            Predicados disponíveis por classe equivalente:<br /><br />
            <table class="pure-table">
                <thead style="vertical-align: middle;">
                    <tr>
                        <th align="center">Classe Domain</th>
                        <th align="center">Predicado</th>
                        <th align="center">Classe Range</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vetPredicados2) > 0) { ?>
                        <?php $i = 1; ?>
                        <?php $j = 0; ?>
                        <?php foreach($vetPredicados2 as $classe => $vetPropriedades) { ?>
                            <?php $apresentaClasse = true; ?>
                            <?php foreach($vetPropriedades as $key => $val) { ?>
                                <?php $classEscuro = ($j++ % 2) ? "" : "";  ?>
                                <tr <?=$classEscuro?> style="border-bottom: 1px solid lightgray;">
                                    <td align="center">
                                        <?php if ($val['domainRange'] == 'D (->)') { echo("<a href='$classe' target='_blank'>".$val['classeLabel']."</a>"); } else { echo(""); } ?>
                                    </td>
                                    <td align="center">
                                        <?="<a href='".$val['predicateURI']."' target='_blank'>".$key?>
                                        <?php
                                            foreach($vetPredicadosEquivalentes2[$val['predicateURI']] as $keyPeq => $valPeq) {
                                                if ($valPeq['predicateURI'] != $val['predicateURI']) {
                                                    echo "<br /><a href='".$valPeq['predicateURI']."' target='_blank' class='linkMenor'>".$keyPeq."(".$valPeq['subEq'].")</a>";
                                                }
                                            }
                                        ?>
                                    </td>
                                    <td align="center">
                                        <?php if ($val['domainRange'] == 'R (<-)') { echo("<a href='$classe' target='_blank'>".$val['classeLabel']."</a>"); } else { echo(""); } ?>
                                    </td>
                                </tr>
                                <?php $apresentaClasse = false; ?>
                            <?php } ?>
                            <?php $i++; ?>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td align="center" colspan="5">Nenhum resultado retornado</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <p style="font-size: small">
                ** <b>Domain</b> do Predicado: Classe(s) que pode(m) ser Sujeito.<br />
                ** <b>Range</b> do Predicado: Classe(s) que pode(m) ser Objeto.
            </p>
        <?php }//end if($realizarBusca2) ?>
    </div>

</div>
<br /><br />
</body>
</html>
