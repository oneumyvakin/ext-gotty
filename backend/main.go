package main

import (
    "flag"
    "log"
    "os/exec"
    "syscall"
    "time"
    "fmt"
    "strings"
    
    "github.com/oneumyvakin/certgen"
)

type runResult struct {
    output string
    outputBytes []byte
    code int
    err error
}

func main() {
    crtFile := flag.String("crt-file", "", "Path where certificate file will be placed")
    keyFile := flag.String("key-file", "", "Path where private key file will be placed")
    run := flag.Bool("run", false, "Run with time-out")
    runTimeout := flag.Int("timeout", 5, "Run timeout in hours")
    runCmd := flag.String("cmd", "", "Run command")
    runCmdArgs := flag.String("cmd-args", "", "Command args")
    flag.Parse()
    
    cmdArgs := strings.Split(*runCmdArgs, " ")
    
    if *crtFile != "" && *keyFile != "" {
        err := certgen.GenerateToFile(certgen.NewDefaultParams(),*crtFile, *keyFile)
        if err != nil {
            log.Fatal(err)
        }
        return
    }
    
    if *run && *runCmd != "" {
        
        exitCode := make(chan runResult, 1)
        go func() {
            result := runResult{}
            result.output, result.outputBytes, result.code, result.err = execute(*runCmd, cmdArgs...)
            
            exitCode <- result
        }()
        
        select {
        case res := <-exitCode:
            if res.code != 0 {
                log.Fatalf("Run command exit: %s exit code: %d", res.output, res.code)
            }
            return
        case <-time.After(time.Duration(*runTimeout) * time.Hour):
            fmt.Println("timeout")
            return
        }
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