package main

import (
    "flag"
    "github.com/0x434D53/certgen"
    "log"
)

func main() {
    crtFile := flag.String("crt-file", "", "Path where certificate file will be placed")
    keyFile := flag.String("key-file", "", "Path where private key file will be placed")
    flag.Parse()
    
    err := certgen.GenerateToFile(certgen.NewDefaultParams(),*crtFile, *keyFile)
	if err != nil {
		log.Fatal(err)
    }
    
}
