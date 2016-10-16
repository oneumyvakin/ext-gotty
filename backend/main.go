package main

import (
    "flag"
    "log"
    
    "github.com/oneumyvakin/certgen"
)

func main() {
    crtFile := flag.String("crt-file", "", "Path where certificate file will be placed")
    keyFile := flag.String("key-file", "", "Path where private key file will be placed")
    flag.Parse()
    
    if *crtFile != "" && *keyFile != "" {
        err := certgen.GenerateToFile(certgen.NewDefaultParams(),*crtFile, *keyFile)
        if err != nil {
            log.Fatal(err)
        }
        return
    }
}