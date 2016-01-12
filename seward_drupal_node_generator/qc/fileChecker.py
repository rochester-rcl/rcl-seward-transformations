#!/usr/bin/env python
import os
import lxml.etree as ET
import argparse
from itertools import izip
import collections
import csv

class FileChecker(object):

    def __init__(self, directory):
        self.directory = directory
        self.report = None

    def fileChecker(self,directory, extension, path):
        fileList = []
        for dirname, dirnames, filenames in os.walk(directory): # Loop through the directory passed from --directory argument
            for inFile in filenames:
                fileInfo = collections.namedtuple('FileInfo', ['directory', 'filename', 'ext'])
                if path == True:
                    filePath =  os.path.abspath(os.path.join(dirname, inFile))
                    fileList.append(fileInfo(directory = dirname, filename = filePath, ext = os.path.splitext(filePath)[1]))
                else:
                    fileList.append(fileInfo(directory = dirname, filename = inFile, ext = os.path.splitext(inFile)[1]))

        extensionsList = [info for info in fileList if info.ext == extension]

        if len(extensionsList) == 0:
            errorInfo = collections.namedtuple('ErrorInfo', ['directory', 'ext', 'reason'])
            errorMsg = errorInfo(directory=dirname, ext=extension, reason='xml file missing')

            return errorMsg
        else:
            return extensionsList

    def domChecker(self, xmlFile, jpgFileList):
        parser = ET.XMLParser(ns_clean=True)
        domTree = ET.parse(xmlFile)
        root = domTree.getroot()
        facs = root.xpath('/tei:TEI/tei:facsimile[1]/tei:graphic', namespaces={'tei': 'http://www.tei-c.org/ns/1.0'})
        returnVal = 'false'

        for graphic, jpgFile in izip(facs, jpgFileList):
            if graphic.attrib['url'] != jpgFile.filename:
                print 'Mismatch! url {} : filename {}'.format(graphic.attrib['url'], jpgFile.filename)
                print 'fixing ...'
                graphic.attrib['url'] = jpgFile.filename
                returnVal = 'true'
            else:
                returnVal = 'false'

        if returnVal == 'true':
            returnTuple = collections.namedtuple('xml', ['tree', 'output', 'reason', 'filename'])
            xml = returnTuple(tree=root, output=xmlFile, reason='url mismatch', filename=xmlFile)
            return xml

    def xmlReport(self):
        jpgFiles = self.fileChecker(self.directory, '.jpg', False)

        xmlFile = self.fileChecker(self.directory, '.xml', True)

        if 'xml file missing' in xmlFile:
            print 'no {} found in {}'.format(xmlFile.ext, xmlFile.directory)
            return xmlFile
        else:
            xmlFixed = self.domChecker(xmlFile[0].filename, jpgFiles)
            if xmlFixed:
                domTree = ET.ElementTree(xmlFixed.tree)
                domTree.write(xmlFixed.output, pretty_print=True)
                return xmlFixed
            else:
                print 'Everything in {} checks out'.format(xmlFile[0].filename)

if __name__ == '__main__':

    #Main Program

    parser = argparse.ArgumentParser(description="Run a script to recursively list the absolute paths of all xml files in a given directory")#Setting up our Argument Parser
    parser.add_argument('-d', '--directory', help="Absolute Path of Directory You Want to Scan", required="true", type=str)#Adding a directory argument
    parser.add_argument('-o', '--output', help="Output csv file", required="true", type=str)#Adding an output argument
    args = vars(parser.parse_args())#Get some variables from parse_args dictionary (directory, output)
    directory = args['directory']
    outCSV = args['output']

    errorList = []
    csvTuples = []
    for dirname, dirnames, filenames in os.walk(directory):

        errorList.append(dirname)


    print len(errorList)

    errorList.pop(0)

    for directoryPath in errorList:
        print directoryPath

        checkFolder = FileChecker(directoryPath)

        test = checkFolder.xmlReport()

        if test:

            if 'xml file missing' in test:
                csvTuples.append((test.directory.split('/')[-1], test.reason))
            elif 'url mismatch' in test:
                csvTuples.append((test.filename.split('/')[-1], test.reason))

            with open(outCSV, 'w') as outFile:
                writer = csv.writer(outFile)
                writer.writerow(('file/folder', 'reason'))
                for row in csvTuples:
                    writer.writerow(row)

        else:
            print 'All files check out!'
