import sys
import json

print('Iniciando...')
file = open("./plain_re/entrada.json", "r", encoding='UTF8')
sys.path.insert(1, './opennre/')
print('[OK]')

print('Carregando OpenNRE...')
import opennre
print('[OK]')

print('Carrengado Modelo BERT...')
model = opennre.get_model('wiki80_bert_softmax')
print('[OK]')

data = {}
data['sugestoes'] = []

print('Criando sugestões...')

with file as f:

    for line in f:
        
        jsonLinha = json.loads(line)

        texto = jsonLinha['text']
        h1 = jsonLinha['h']['pos'][0]
        h2 = jsonLinha['h']['pos'][1]
        t1 = jsonLinha['t']['pos'][0]
        t2 = jsonLinha['t']['pos'][1]
        
        tupleModelInfer = model.infer({'text': texto, 'h': {'pos': (h1, h2)}, 't': {'pos': (t1, t2)}})
        
        data_set = {"predicado": tupleModelInfer[0], "indice": tupleModelInfer[1]}
        data['sugestoes'].append(data_set)

        json_dump = json.dumps(data)

        json_object = json.loads(json_dump)

        sorted_sugestoes = dict(json_object) 
        sorted_sugestoes["sugestoes"] = sorted(json_object["sugestoes"], key=lambda x: x["indice"], reverse=True)

print('[OK]')

with open("./plain_re/saida.json", 'w') as outfile:
     json.dump(sorted_sugestoes, outfile)

print('Sugestões criadas com sucesso!')
