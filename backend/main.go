package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"log"
	"math/rand"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/oneumyvakin/certgen"
)

type frontEndData struct {
	Port int
	User string
	Pass string
}

func main() {
	genTls := flag.Bool("generate-tls", false, "Generate TLS certificate")
	crtFile := flag.String("crt-file", "", "Path where certificate file will be placed or taken")
	keyFile := flag.String("key-file", "", "Path where private key file will be placed or taken")
	configPath := flag.String("config", "", "Path to file where gotty's config will be stored")
	portRangeStart := flag.Int("port-range-start", 9000, "Port range start")
	portRangeStop := flag.Int("port-range-stop", 10000, "Port range stop")
	runTimeout := flag.Int("timeout", 5, "Run timeout in hours")
	gottyPath := flag.String("gotty", "", "Path to gotty binary")
	runCmdArgs := flag.String("cmd-args", "", "Gotty args")
	flag.Parse()

	if *genTls {
		if *crtFile == "" || *keyFile == "" {
			log.Fatal("Failed to generate TLS certificate: Not enough arguments: crt-file or key-file are empty")
		}
		err := certgen.GenerateToFile(certgen.NewDefaultParams(), *crtFile, *keyFile)
		if err != nil {
			log.Fatal(fmt.Errorf("Failed to generate TLS certificate: %s", err))
		}
		return
	}

	if *configPath == "" || *crtFile == "" || *keyFile == "" || *gottyPath == "" || *runCmdArgs == "" {
		log.Fatal("Failed to run gotty: Not enough arguments: gotty, config, cmd-args, crt-file or key-file are empty")
	}

	cmdArgs := strings.Split(*runCmdArgs, " ")

	go func() {
		time.Sleep(time.Duration(*runTimeout) * time.Hour)
		os.Exit(0)
	}()

	portChan := make(chan int, 1)
	go acquirePort(portChan, *portRangeStart, *portRangeStop)
	toFrontEnd := frontEndData{
		Port: <-portChan,
		User: getRandomString(6),
		Pass: getRandomString(6),
	}

	err := createGottyConfig(*configPath, toFrontEnd.Port, *crtFile, *keyFile, toFrontEnd.User, toFrontEnd.Pass)
	if err != nil {
		log.Fatal(fmt.Errorf("Failed to create gotty's config: %s", err))
	}

	err = createFrontEndConfig(filepath.Join(filepath.Dir(*configPath), "front-end.json"), toFrontEnd)
	if err != nil {
		log.Fatal(fmt.Errorf("Failed to create front-end config: %s", err))
	}

	output, _, code, err := execute(*gottyPath, cmdArgs...)
	if code != 0 {
		log.Fatalf("Run command exit: %s exit code: %d", output, code)
	}

	return
}

func createGottyConfig(configPath string, port int, crtFile string, keyFile string, user string, pass string) error {
	config := map[string]string{
		"port":              fmt.Sprintf("%d", port),
		"enable_tls":        "true",
		"tls_crt_file":      fmt.Sprintf("\"%s\"", crtFile),
		"tls_key_file":      fmt.Sprintf("\"%s\"", keyFile),
		"enable_basic_auth": "true",
		"credential":        fmt.Sprintf("\"%s:%s\"", user, pass),
	}

	configText := ""
	for param, value := range config {
		configText += fmt.Sprintf("%s = %s\n", param, value)
	}

	err := ioutil.WriteFile(configPath, []byte(configText), 0644)
	if err != nil {
		return err
	}
	return nil
}

func createFrontEndConfig(configPath string, data frontEndData) error {
	config, err := json.Marshal(data)
	if err != nil {
		return err
	}
	err = ioutil.WriteFile(configPath, config, 0644)
	if err != nil {
		return err
	}
	return nil
}

func getRandomString(n int) string {
	const letterBytes = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
	const (
		letterIdxBits = 6                    // 6 bits to represent a letter index
		letterIdxMask = 1<<letterIdxBits - 1 // All 1-bits, as many as letterIdxBits
		letterIdxMax  = 63 / letterIdxBits   // # of letter indices fitting in 63 bits
	)

	var src = rand.NewSource(time.Now().UnixNano())

	b := make([]byte, n)
	// A src.Int63() generates 63 random bits, enough for letterIdxMax characters!
	for i, cache, remain := n-1, src.Int63(), letterIdxMax; i >= 0; {
		if remain == 0 {
			cache, remain = src.Int63(), letterIdxMax
		}
		if idx := int(cache & letterIdxMask); idx < len(letterBytes) {
			b[i] = letterBytes[idx]
			i--
		}
		cache >>= letterIdxBits
		remain--
	}

	return string(b)
}

func acquirePort(portChan chan int, portRangeStart int, portRangeStop int) {
	var listener net.Listener
	var err error
	var port int

	for port = portRangeStart; port <= portRangeStop; port++ {
		listener, err = net.Listen("tcp", fmt.Sprintf(":%d", port)) // Just to find free port
		if err != nil {
			continue
		}
		listener.Close()

		portChan <- port
		break
	}
	if err != nil {
		log.Fatalf("Failed to acquire free TCP port: %s", err)
	}
}

func execute(command string, args ...string) (output string, outputBytes []byte, code int, err error) {
	cmd := exec.Command(command, args...)
	var waitStatus syscall.WaitStatus

	if outputBytes, err = cmd.CombinedOutput(); err != nil {
		// Did the command fail because of an unsuccessful exit code
		if exitError, ok := err.(*exec.ExitError); ok {
			waitStatus = exitError.Sys().(syscall.WaitStatus)
			code = waitStatus.ExitStatus()
		}
	} else {
		// Command was successful
		waitStatus = cmd.ProcessState.Sys().(syscall.WaitStatus)
		code = waitStatus.ExitStatus()
	}

	output = string(outputBytes)
	return
}
