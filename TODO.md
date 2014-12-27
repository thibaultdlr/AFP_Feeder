#TODO AFP Feeder beta :

rediger readme avec breves instructions options admin et widget

##TODO optionnel :

- diviser load_xml() en load_xml_index() pour eter load_xml dès sauvegarde des options (possible necessité d'ajouter membre xml_index dand AFP_Feeder)

- utiliser autre choses que settings_erroe por le rapport d'import (simple tableau bi-dim ave pour chaque depêche ['message'] + ['imported' | 'error' ]

##TODO si v2 :

- contacter AFP pour :
  - connaître structure exacte offre texte et différent cas de figures du repertoire de dépot
  - savoir si offre/api existente pour integration sous wp

- selon reponse afp,
  - creer post_type afpfeeder_instance avec dans le contenu les options specifiques à un feed
  - implementer wp_table_list pour les afpfeeder_instance(s) + page edit = page d'options actuelle
  - quid de la relation 1 afpfeeder_instance > les afpfeeds correspondant ?
  - options generales : import auto + recurrence ? Ou pas d'options spécifiques ? 