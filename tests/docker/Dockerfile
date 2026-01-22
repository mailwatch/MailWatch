services:
  ldap:
    image: osixia/openldap:latest
    container_name: ldap-server
    environment:
      LDAP_ORGANISATION: "Test Company"
      LDAP_DOMAIN: "test.local"
      LDAP_ADMIN_PASSWORD: "admin"
#      HOSTNAME: "ldap.test.local"
#      LDAP_TLS_CRT_FILENAME: "ldap-server-cert.pem"
#      LDAP_TLS_KEY_FILENAME: "ldap-server-key.pem"
#      LDAP_TLS_CA_CRT_FILENAME: "ca-cert.pem"
#    volumes:
#        - ./ldap/certs/ldap-server-cert.pem:/container/service/slapd/assets/certs/ldap-server-cert.pem
#        - ./ldap/certs/ldap-server-key.pem:/container/service/slapd/assets/certs/ldap-server-key.pem
#        - ./ldap/certs/ca-cert.pem:/container/service/slapd/assets/certs/ca-cert.pem
    ports:
#      - "389:389"
      - "636:636"


  phpldapadmin:
    image: osixia/phpldapadmin:latest
    container_name: phpldapadmin
    environment:
      PHPLDAPADMIN_LDAP_HOSTS: "ldap-server"
#      PHPLDAPADMIN_LDAP_HOSTS: "#PYTHON2BASH:[{'ldap-server': [{'server': [{'tls': True}]}]}]"
      PHPLDAPADMIN_LDAP_CLIENT_TLS: "true"
      PHPLDAPADMIN_HTTPS: "false"
    ports:
      - "8389:80"
