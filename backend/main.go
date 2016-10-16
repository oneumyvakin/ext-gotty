package main

import (
    "flag"
    "github.com/0x434D53/certgen"
    "log"
    "net"
    "fmt"
)

func main() {
    crtFile := flag.String("crt-file", "", "Path where certificate file will be placed")
    keyFile := flag.String("key-file", "", "Path where private key file will be placed")
    getFreePort := flag.Bool("get-free-port", false, "Finds free TCP port in port range")
    portStart := flag.Int("port-start", 9000, "Start port range")
    portEnd := flag.Int("port-end", 10000, "End  port range")
    flag.Parse()
    
    if *crtFile != "" && *keyFile != "" {
        err := certgen.GenerateToFile(certgen.NewDefaultParams(),*crtFile, *keyFile)
        if err != nil {
            log.Fatal(err)
        }
        return
    }
    
    if *getFreePort {
        for i:=*portStart; i <= *portEnd; i++ {
            _, err := net.Listen("tcp", fmt.Sprintf(":%d", i))
            if err != nil {
                continue
            }
            fmt.Printf("%d", i)
            return
        }
        log.Fatal("Failed to find free TCP port: All ports in range are busy.")
    }
    
}
